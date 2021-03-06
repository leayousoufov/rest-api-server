<?php

namespace Fuzz\ApiServer\Routing;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Support\Arrayable;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;
use League\OAuth2\Server\Exception\OAuthException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Responder
{
	/**
	 * Send a response.
	 *
	 * @param mixed   $data
	 * @param int     $status_code
	 * @param array   $headers
	 * @param boolean $json
	 * @return \Illuminate\Http\JsonResponse
	 */
	final public function send($data, $status_code = Response::HTTP_OK, array $headers = [], $json = true)
	{
		if ($data instanceof Arrayable) {
			$data = $data->toArray();
		}

		$headers['Access-Control-Allow-Origin'] = '*';

		if ($json) {
			return new JsonResponse($data, $status_code, $headers);
		}

		return response($data, $status_code, $headers);
	}

	/**
	 * Notify the caller of failure.
	 *
	 * @param \Exception $exception
	 * @return \Illuminate\Http\JsonResponse
	 */
	final public function sendException(\Exception $exception)
	{
		/**
		 * Handle known HTTP exceptions RESTfully.
		 */
		if ($exception instanceof OAuthException) {
			$error             = $exception->errorType;
			$error_description = $exception->getMessage();
			$status_code       = $exception->httpStatusCode;
			$headers           = $exception->getHttpHeaders();
		} elseif ($exception instanceof HttpException) {
			$error             = snake_case(class_basename($exception));
			$error_description = $exception->getMessage();
			$status_code       = $exception->getStatusCode();
			$headers           = $exception->getHeaders();
		} else {
			/**
			 * Contextualize response with verbose information outside production.
			 *
			 * Report only "unknown" errors in production.
			 */
			$error = 'unknown';

			if (Config::get('app.debug')) {
				$error_description = [
					'message' => $exception->getMessage(),
					'class'   => get_class($exception),
					'file'    => $exception->getFile(),
					'line'    => $exception->getLine(),
				];
			}

			if ($exception instanceof HttpException) {
				$status_code = $exception->getStatusCode();
				$headers = $exception->getHeaders();
			} else {
				$status_code = Response::HTTP_INTERNAL_SERVER_ERROR;
				$headers = [];
			}
		}

		return $this->send(
			compact('error', 'error_description', 'error_data'), $status_code, $headers
		);
	}
}
