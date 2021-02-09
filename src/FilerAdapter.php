<?php


namespace HocVT\Flysystem\Filer;


use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\Adapter\Polyfill\StreamedWritingTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Psr\Http\Message\ResponseInterface;

class FilerAdapter implements AdapterInterface
{

    use StreamedWritingTrait;

    /** @var Client  */
    protected $client;

    /**
     * FilerAdapter constructor.
     * @param $client
     */
    public function __construct($filer_host = 'http://127.0.0.1:8888')
    {
        $this->client = new Client([
            'base_uri' => $filer_host
        ]);
    }


    public function write($path, $contents, Config $config)
    {
        if(!$contents){
            $contents = ' ';//trick to set header for file
        }
        $file_name = basename($path);
        $headers = $this->makeHeader($config);
        try{
            $response = $this->client->post($path, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $contents,
                        'filename' => $file_name
                    ]
                ],
                'headers' => $headers
            ]);
            return $this->getMetadata($path);
        }catch (\Exception $ex){
            return false;
        }

    }

    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config)
    {
        // TODO: Implement updateStream() method.
    }

    public function rename($path, $newpath)
    {
        if($this->copy($path, $newpath)){
            return $this->delete($path);
        }else{
            return false;
        }
    }

    public function copy($path, $newpath)
    {
        $old = $this->read($path);
        if($this->write($newpath, $old['contents'], new Config())){
            return true;
        }else{
            return false;
        }
    }

    public function delete($path)
    {
        try{
            $this->client->delete($path);
            return true;
        }catch (\Exception $ex){
            return false;
        }

    }

    public function deleteDir($dirname)
    {
        try{
            $this->client->delete(rtrim($dirname, "/") . "/", [
                'query' => [
                    'recursive' => 'true'
                ]
            ]);
            return true;
        }catch (RequestException $ex){
            return false;
        }
    }

    public function createDir($dirname, Config $config)
    {
        if($meta = $this->getMetadata($dirname)){
            if($meta['type'] == 'dir'){
                return $meta;
            }else{
                return false;
            }
        }
        try{
            $this->client->post(rtrim($dirname, "/") . "/");
            return $this->getMetadata($dirname);
        }catch (RequestException $ex){
            return false;
        }

    }

    public function setVisibility($path, $visibility)
    {
        try{
            $response = $this->client->put($path, [
                'query' => [
                    'tagging' => true
                ],
                'headers' => [
                    'Seaweed-visibility' => $visibility,
                ]
            ]);
            return $this->getMetadata($path);
        }catch (RequestException $exception){
            return false;
        }
    }

    public function has($path)
    {
        try{
            $response = $this->client->head($path);
            return true;
        }catch (RequestException $ex){
            return false;
        }
    }

    public function read($path)
    {
        try{
            $response = $this->client->get($path);
            $ct = $response->getHeader('Content-Length');
            $content = $response->getBody()->getContents();
            if(count($ct)){
                return [
                    'path' => $path,
                    'contents' => $content,
                ];
            }else{
                return false;
            }
        }catch (RequestException $ex){
            return false;
        }
    }

    public function readStream($path)
    {
        try{
            $response = $this->client->get($path);
            $ct = $response->getHeader('Content-Length');
            if(count($ct)){
                return [
                    'path' => $path,
                    'stream' => $response->getBody()->detach(),
                ];
            }else{
                return false;
            }
        }catch (RequestException $ex){
            return false;
        }
    }

    public function listContents($directory = '', $recursive = false)
    {
        try{

            $result = $this->listContentsBase($directory);

            if($recursive == true){
                $recursive_result = [];
                foreach ($result as $item){
                    if($item['type'] == 'dir'){
                        $recursive_result = array_merge($recursive_result, $this->listContentsBase($item['path']));
                    }
                }
                $result = array_merge($result, $recursive_result);
            }

            return $result;

        }catch (RequestException $exception){
            return [];
        }
    }

    protected function listContentsBase($directory){
        $response = $this->client->get(
            rtrim($directory, "/") . "/?pretty=y",
            [
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

        $data = json_decode($response->getBody()->getContents(), true);

        $result = [];

        foreach ($data['Entries'] as $file_data){
            $result[] = $this->parseFileInfo($file_data);
        }
        return $result;
    }

    public function getMetadata($path)
    {
        try{
            $response = $this->client->head($path);
            $ct = $response->getHeader('Content-Length');
            if(count($ct)){
                $fallback_timestamp = $this->parseDate($response->getHeader('Last-Modified')[0]);
                return [
                    'path' => $path,
                    'size' => intval($ct[0]),
                    'type' => 'file',
                    'visibility' => $this->getHeaderFromResponse($response, 'Seaweed-visibility', AdapterInterface::VISIBILITY_PUBLIC),
                    'timestamp' => (int)$this->getHeaderFromResponse($response, 'Seaweed-timestamp', $fallback_timestamp),
                ] + $this->getMimetypeFromRes($response);
            }elseif($response->getStatusCode() == 204){// no content
                return [
                        'path' => $path,
                        'size' => 0,
                        'type' => 'file',
                        'visibility' => $this->getHeaderFromResponse($response, 'Seaweed-visibility', AdapterInterface::VISIBILITY_PUBLIC),
                        'timestamp' => $this->parseDate($response->getHeader('Date')[0]),
                    ] + $this->getMimetypeFromRes($response);
            }else{
                return [
                    'path' => $path,
                    'type' => 'dir',
                    'timestamp' => time(),
                ];
            }

        }catch (RequestException $exception){
            return false;
        }
    }

    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    public function getMimetype($path)
    {
        try{
            $response = $this->client->head($path);
            $ct = $response->getHeader('Content-Length');
            if(count($ct)){
                return [
                        'path' => $path,
                    ] + $this->getMimetypeFromRes($response, true);
            }elseif($response->getStatusCode() == 204){
                return [
                        'path' => $path,
                    ] + $this->getMimetypeFromRes($response, true);
            }else{
                return [
                    'path' => $path,
                    'type' => 'dir',
                    'timestamp' => time(),
                ];
            }

        }catch (RequestException $exception){
            return false;
        }
    }

    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    public function getVisibility($path)
    {
        return $this->getMetadata($path);
    }

    protected function parseDate($string){
        try{
            return Carbon::createFromLocaleFormat("D, d M Y H:i:s e","en",$string)->timestamp;
        }catch (\Exception $ex){
            return time();
        }
    }

    protected function getMimetypeFromRes(ResponseInterface $response, $auto = false){
        $header = $response->getHeader('Seaweed-mimetype');
        $header1 = $response->getHeader('Content-type');
        if(count($header)){
            return [
                'mimetype' => $header[0],
            ];
        }elseif($auto && count($header1)){
            return [
                'mimetype' => $header1[0],
            ];
        }else{
            return [];
        }
    }

    /**
     * @param ResponseInterface $response
     * @param $header_name
     * @param null $default
     * @return mixed|string|null
     */
    protected function getHeaderFromResponse(ResponseInterface $response, $header_name, $default = null){
        $header = $response->getHeader($header_name);
        return $header[0] ?? $default;
    }

    protected function makeHeader(Config $config){
        $headers = [
            'Seaweed-timestamp' => $config->get('timestamp', time()),
            'Seaweed-visibility' => $config->get('visibility', AdapterInterface::VISIBILITY_PUBLIC),
        ];
        if($mimetype = $config->get('mimetype')){
            $headers['Seaweed-mimetype'] = $mimetype;
        }
        return $headers;
    }

    protected function parseFileInfo($file_info){
        if(isset($file_info['chunks'])){
            $size = array_reduce($file_info['chunks'], function($_size, $chunk){
                return $_size + $chunk['size'];
            }, 0);
        }else{
            $size = 0;
        }
        $info = [
            'path' => ltrim($file_info['FullPath'], '/'),
            'size' => $size,
            'mimetype' => $file_info['Mime'],
            'type' => $file_info['Mime'] ? "file" : "dir",
        ];
        return $info;
    }

}