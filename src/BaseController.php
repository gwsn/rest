<?php
namespace Gwsn\Rest;

use Log;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    protected $count = 1;
    protected $totalCount = 1;
    protected $page = 1;
    protected $limit = 5;

    /**
     * @var string $sortingKey
     */
    protected $sortingKey = 'id';

    /**
     * Possible sorting
     * @var string $sortingDirection
     */
    protected $sortingDirection = 'asc';

    protected $sortingErrors = [];

    /**
     * @param array $data
     * @param string $key
     * @param string $direction Possible values ['asc', 'ascending', 'desc', 'descending']
     *
     * @throws \Exception
     * @return array
     */
    protected function sorting(array $data = [], $key = null, $direction = null) {
        $key        = ($key !== null ?          $key                    : $this->sortingKey);
        $direction  = ($direction !== null ?    strtolower($direction)  : $this->sortingDirection);

        if(!in_array($direction, ['asc', 'ascending', 'desc', 'descending']))
            throw new \Exception('Not a valid direction '.$direction, 1);

        if(empty($data))
            return $data;

        // Check if sorting key exists
        if(!key_exists($key, array_values($data)[0])) {
            throw new \Exception('Cannot sort this array because the key "'.$key.'" not exists', 1);
        }

        // Sort $data by 'key' property, ascending
        if(in_array($direction, ['asc' , 'ascending'])) {
            usort($data, function ($a, $b) use ($key){
                return $a[$key] <=> $b[$key];
            });
        }

        // Sort $data by 'key' property, descending
        else {
            usort($data, function ($a, $b) use ($key) {
                return $b[$key] <=> $a[$key];
            });
        }

        return $data;
    }

    protected function notFoundResponse(Request $request, $message = '') {
        Log::notice('404: Resource not found');

        return $this->response($request, [], [], [
            'code' => 404,
            'message' => (!empty($message) ? $message : 'Not found'),
        ]);
    }

    protected function badRequestResponse(Request $request, $message = '') {
        Log::info('400: Bad Request. input: '.json_encode($request->all()));
        return $this->response($request, [], [], [
            'code' => 400,
            'message' => (!empty($message) ? $message : 'Bad Request, some of the given params are not correct try to adjust them.'),
        ]);
    }

    protected function failedResponse(Request $request, $message = '', $statusCode = 401) {
        Log::notice ($statusCode . ': Something went wrong with the call');

        return $this->response($request, [], [], [
            'code' => $statusCode,
            'message' => (!empty($message) ? $message : $statusCode. ': Something went wrong, try to adjust your search query'),
        ]);
    }

    /**
     * @param Request $request
     * @param $data
     * @param array $meta
     * @param array $status
     *
     * @return mixed
     */
    protected function response(Request $request, $data, array $meta = [], array $status = []) {

        $meta = self::buildMetaData($request, $data, $meta);
        $status = self::buildStatusCode($status);

        // Try Sorting
        $sortingKey = $request->get('sortKey', null);
        $sortingDir = $request->get('sortDir', null);

        if($sortingDir !== null && $sortingKey !== null) {
            try {
                $meta['autoSorting'] = [
                    'status' => 'true',
                    'error' => null,
                ];
                $data = $this->sorting($data, $sortingKey, $sortingDir);
            } catch( \Exception $e) {
                Log::notice($e->getMessage());
                $meta['autoSorting'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $response = [
            'data' => $data,
            'metadata' => $meta,
            'status' => $status,
        ];
        return response()->json($response, $response['status']['code']);
    }

    /**
     * @param array $status
     *
     * @return array
     */
    private static function buildStatusCode(array $status = []) {
        $statusData = ['code' => 200, 'message' => 'ok'];

        if(!empty($status)) {
            return array_merge($statusData, $status);
        }

        return $statusData;
    }

    /**
     * @param Request $request
     * @param array   $metaData
     *
     * @return array
     */
    private static function buildMetaData(Request $request, $data, array $metaData = []) {

        $meta = [
            'count' => count((array) $data),
            'totalCount' => count((array) $data),
            'page' => 1,
            'input' => $request->all(),
            'debug' => (app()->environment() !== 'production' ? self::createDebugMetaData($request) : null),
            'autoSorting' => [
                'status' => 'false',
                'error' => null,
            ],
        ];

        if(!empty($metaData)) {
            $meta = array_merge($metaData, $meta);
        }

        return $meta;
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    private static function createDebugMetaData(Request $request) {

        return [
            'clientIP' => (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null),
            'serverIP' => (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null),
            'serverName' => (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null),
            'serverSig' => (isset($_SERVER['SERVER_SIGNATURE']) ? $_SERVER['SERVER_SIGNATURE'] : null),

            'request' => [

                'method' => $request->method(),
                'path' => $request->getPathInfo(),
                'domain' => $request->root(),
                'full_url' => $request->fullUrl(),
                'proxy' => $request->get('proxy', false),
                'params' => $request->all(),
                'cacheHit' => null,

            ],
            'environment' =>  app()->environment(),
        ];
    }
}