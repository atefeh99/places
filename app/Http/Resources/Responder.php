<?php
namespace App\Http\Resources;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\Response;

class Responder
{
    /**
     * @var int
     */
    private $status_code;
    /**
     * @var int
     */
    private $code;
    /**
     * @var string
     */
    private $message;


    /**
     * @param $code int
     * @param $fields MessageBag
     * @param string $message
     * @return mixed
     */
    public  function respondInvalidParams($code, $fields, $message = '')
    {
        if (!$message) {
            $message = trans('messages.custom.' . Response::HTTP_BAD_REQUEST);
        }
        return $this
            ->setCode($code)
            ->setStatusCode(Response::HTTP_BAD_REQUEST)
            ->setMessage($message)
            ->respondWithParamsError($fields);
    }

    /**
     * return object result in returning one item response
     * @param $data
     * @param $count
     * @return mixed
     */
    public  function respondArrayResult($data ,$count)
    {
        return $this
            ->setStatusCode(Response::HTTP_OK)
            ->respond([
                'odata.count' => $count,
                'value' => $data
            ]);
    }

    /**
     * @param $message
     * @param $code
     * @return mixed
     */
    public  function respondNoFound($message,$code)
    {
        return $this
            ->setStatusCode(Response::HTTP_NOT_FOUND)
            ->setMessage($message)
            ->setCode($code)
            ->respondWithError();
    }
    /**
     * @return mixed
     */
    private function respondWithError()
    {
        return $this->respond([
            'status' => $this->getStatusCode(),
            'message' => $this->getMessage(),
            'code' => $this->getCode()
        ]);
    }

    /**
     * @param $message
     * @param $status_code
     * @param $code
     * @return mixed
     */
    public function respondError($message, $status_code, $code)
    {
        if ($message === 'unauthorized') {
            $message = trans('messages.custom.' . Response::HTTP_UNAUTHORIZED);
        }
        return $this
            ->setCode($code)
            ->setStatusCode($status_code)
            ->setMessage($message)
            ->respondWithError();
    }

    /**
     * @param $fields
     * @return mixed
     */
    private  function respondWithParamsError($fields)
    {

        return $this->respond([
            'status' => self::getStatusCode(),
            'message' => self::getMessage(),
            'fields' => $fields,
            'code' => self::getCode()
        ]);
    }

    /**
     * return object result in returning one item response
     * @param $data
     * @return mixed
     */
    public function respondItemResult($data)
    {
        return $this
            ->setStatusCode(Response::HTTP_OK)
            ->respond([
                'data' => $data
            ]);
    }

    /**
     * @param $code
     * @return $this
     */
    private  function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @param $message
     * @return $this
     */
    private  function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }
    /**
     * @param $status_code
     * @return $this
     */
    private  function setStatusCode($status_code)
    {
        $this->status_code = $status_code;
        return $this;
    }


    /**
     * @return int
     */
    private function getStatusCode()
    {
        return $this->status_code;
    }

    /**
     * @return int
     */
    private function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     */
    private function getMessage()
    {
        return $this->message;
    }
    /**
     * @param $data
     * @param array $headers
     * @return mixed
     */
    public  function respond($data, $headers = [])
    {
        return response()->json($data, $this->getStatusCode(), $headers);
    }
}

