<?php

namespace Directus\Api\Routes;

use Directus\Application\Application;
use Directus\Application\Http\Request;
use Directus\Application\Http\Response;
use Directus\Application\Route;
use Directus\Database\Exception\FieldNotFoundException;
use Directus\Database\Exception\CollectionNotFoundException;
use Directus\Exception\ErrorException;
use Directus\Exception\UnauthorizedException;
use Directus\Services\TablesService;
use Directus\Util\ArrayUtils;
use Directus\Util\StringUtils;

class Fields extends Route
{
    /**
     * @param Application $app
     */
    public function __invoke(Application $app)
    {
        $app->post('/{collection}', [$this, 'create']);
        $app->get('/{collection}/{field}', [$this, 'read']);
        $app->patch('/{collection}/{field}', [$this, 'update']);
        $app->patch('/{collection}', [$this, 'update']);
        $app->delete('/{collection}/{field}', [$this, 'delete']);
        $app->get('/{collection}', [$this, 'all']);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws UnauthorizedException
     */
    public function create(Request $request, Response $response)
    {
        $this->validateRequestPayload($request);
        $service = new TablesService($this->container);
        $payload = $request->getParsedBody();
        $field = ArrayUtils::pull($payload, 'field');

        $responseData = $service->addColumn(
            $request->getAttribute('collection'),
            $field,
            $payload,
            $request->getQueryParams()
        );

        return $this->responseWithData($request, $response, $responseData);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws FieldNotFoundException
     * @throws CollectionNotFoundException
     * @throws UnauthorizedException
     */
    public function read(Request $request, Response $response)
    {
        $collectionName = $request->getAttribute('collection');
        $fieldName = $request->getAttribute('field');
        $fieldsName = StringUtils::csv((string) $fieldName);

        $service = new TablesService($this->container);
        if (count($fieldsName) > 1) {
            $responseData = $service->findFields($collectionName, $fieldsName, $request->getQueryParams());
        } else {
            $responseData = $service->findField($collectionName, $fieldName, $request->getQueryParams());
        }

        return $this->responseWithData($request, $response, $responseData);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws UnauthorizedException
     */
    public function update(Request $request, Response $response)
    {
        $this->validateRequestPayload($request);
        $service = new TablesService($this->container);
        $field = $request->getAttribute('field');
        $payload = $request->getParsedBody();

        if (
            (isset($payload[0]) && is_array($payload[0]))
            || strpos($field, ',') > 0
        ) {
            return $this->batch($request, $response);
        }

        $responseData = $service->changeColumn(
            $request->getAttribute('collection'),
            $request->getAttribute('field'),
            $request->getParsedBody(),
            $request->getQueryParams()
        );

        return $this->responseWithData($request, $response, $responseData);
    }

    /**
     * Get all columns that belong to a given table
     *
     * NOTE: Maybe this should be on /tables instead
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws CollectionNotFoundException
     * @throws UnauthorizedException
     */
    public function all(Request $request, Response $response)
    {
        $service = new TablesService($this->container);
        $responseData = $service->findAllFields(
            $request->getAttribute('collection'),
            $request->getQueryParams()
        );

        return $this->responseWithData($request, $response, $responseData);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws ErrorException
     * @throws UnauthorizedException
     */
    public function delete(Request $request, Response $response)
    {
        $service = new TablesService($this->container);

        $service->deleteField(
            $request->getAttribute('collection'),
            $request->getAttribute('field'),
            $request->getQueryParams()
        );

        $response = $response->withStatus(204);

        return $this->responseWithData($request, $response, []);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws \Exception
     */
    protected function batch(Request $request, Response $response)
    {
        $tablesService = new TablesService($this->container);

        $collection = $request->getAttribute('collection');
        $tablesService->throwErrorIfSystemTable($collection);

        $payload = $request->getParsedBody();
        $params = $request->getQueryParams();

        if ($fields = $request->getAttribute('field')) {
            $ids = explode(',', $fields);
            $responseData = $tablesService->batchUpdateFieldWithIds($collection, $ids, $payload, $params);
        } else {
            $responseData = $tablesService->batchUpdateField($collection, $payload, $params);
        }

        if (empty($responseData)) {
            $response = $response->withStatus(204);
            $responseData = [];
        }

        return $this->responseWithData($request, $response, $responseData);
    }
}
