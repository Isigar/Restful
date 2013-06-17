<?php
namespace Drahak\Restful;

use Drahak\Restful\IResource;
use Drahak\Restful\Http\IRequest;
use Drahak\Restful\Resource\EnvelopeDecorator;
use Drahak\Restful\Utils\RequestFilter;
use Nette\Http\IResponse;
use Nette\Object;

/**
 * REST ResponseFactory
 * @package Drahak\Restful
 * @author Drahomír Hanák
 */
class ResponseFactory extends Object implements IResponseFactory
{

	/** @var IResponse */
	private $response;

	/** @var IRequest */
	private $request;

	/** @var RequestFilter */
	private $filter;

	/** @var array */
	private $responses = array(
		IResource::JSON => 'Drahak\Restful\Application\Responses\JsonResponse',
		IResource::JSONP => 'Drahak\Restful\Application\Responses\JsonpResponse',
		IResource::QUERY => 'Drahak\Restful\Application\Responses\QueryResponse',
		IResource::XML => 'Drahak\Restful\Application\Responses\XmlResponse',
		IResource::DATA_URL => 'Drahak\Restful\Application\Responses\DataUrlResponse',
		IResource::NULL => 'Drahak\Restful\Application\Responses\NullResponse'
	);

	public function __construct(IResponse $response, IRequest $request, RequestFilter $filter)
	{
		$this->response = $response;
		$this->request = $request;
		$this->filter = $filter;
	}

	/**
	 * Register new response type to factory
	 * @param string $mimeType
	 * @param string $responseClass
	 * @return $this
	 *
	 * @throws InvalidArgumentException
	 */
	public function registerResponse($mimeType, $responseClass)
	{
		if (!class_exists($responseClass)) {
			throw new InvalidArgumentException('Response class does not exist.');
		}

		$this->responses[$mimeType] = $responseClass;
		return $this;
	}

	/**
	 * Unregister API response fro mfactory
	 * @param string $mimeType
	 */
	public function unregisterResponse($mimeType)
	{
		unset($this->responses[$mimeType]);
	}

	/**
	 * Create new api response
	 * @param IResource $resource
	 * @param int|null $code
	 * @return IResponse
	 *
	 * @throws InvalidStateException
	 */
	public function create(IResource $resource, $code = NULL)
	{
		$contentType = $resource->getContentType();
		if (!isset($this->responses[$contentType])) {
			throw new InvalidStateException('Unregistered API response.');
		}

		if (!class_exists($this->responses[$contentType])) {
			throw new InvalidStateException('API response class does not exist.');
		}

		if ($this->request->isJsonp()) {
			$contentType = IResource::JSONP;
		}

		$this->setupCode($resource, $code);
		$this->setupPaginator($resource, $code);

		$responseClass = $this->responses[$contentType];
		$response = new $responseClass($resource->getData());
		return $response;
	}

	/**
	 * Setup response HTTP code
	 * @param IResource $resource
	 * @param int|null $code
	 */
	protected function setupCode(IResource $resource, $code = NULL)
	{
		if ($code === NULL) {
			$code = 200;
			if (!$resource->getData()) {
				$code = 204; // No content
			}
		}
		$this->response->setCode($code);
	}

	/**
	 * Setup paginator
	 * @param IResource $resource
	 * @param int|null $code
	 */
	protected function setupPaginator(IResource $resource, $code = NULL)
	{
		try {
			$paginator = $this->filter->getPaginator();
			$paginator->setUrl($this->request->getUrl());

			$link = '<' . $paginator->getNextPageUrl() . '>; rel="next"';

			if ($paginator->getItemCount()) {
				$link .= ', <' . $paginator->getLastPageUrl() . '>; rel="last"';
				$this->response->setHeader('X-Total-Count', $paginator->getItemCount());
			}
			$this->response->setHeader('Link', $link);
		} catch (InvalidStateException $e) {
			// Don't use paginator
		}
	}

}