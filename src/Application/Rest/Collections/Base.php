<?php

namespace Phalconify\Application\Rest\Collections;

use Phalcon\Db\Adapter\MongoDB\Model\BSONDocument;
use \Phalconify\Application\Rest\Http\Request;

/**
 * Implements an abstraction for collections.
 */
abstract class Base extends \Phalcon\Mvc\MongoCollection implements \JsonSerializable
{
    use Loader;
    use Pagination;
    use Sorting;

    public $dateUpdated;

    public $dateCreated;

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        $this->_id = (string)$this->_id;

        return $this->getData();
    }

    /**
     * Gets the conditions to apply.
     *
     * @param array $query
     *                     Query parameters
     *
     * @return array
     */
    public static function getConditions($query = [])
    {
        $properties = self::getProperties();

        return array_intersect_key($query, array_flip($properties));
    }

    /**
     * Fetch all fields based on pagination filter and sorting filter, key limited.
     *
     * @return array
     */
    public static function fetch($query = [])
    {
        $filter = self::getPaginationFilter($query);
        $filter += self::getSortingFilter($query);
        $filter += [
            'fields' => static::getProperties(),
            'conditions' => static::getConditions($query),
        ];

        return static::find($filter);
    }

    /**
     * Gets total number of entries matched by the query.
     *
     * @param array $query
     *                     Query
     *
     * @return int
     */
    public static function total($query = [])
    {
        return static::count(['conditions' => static::getConditions($query)]);
    }

    /**
     * Return a string interpretation of the of error messages.
     *
     * @return string
     */
    public function getMessagesAsString()
    {
        $messages = $this->getMessages();

        return implode(', ', $messages);
    }

    /**
     * Return messages without associative keys.
     *
     * @return array
     */
    public function getMessagesAsArray()
    {
        return array_map(function (\Phalcon\Mvc\Model\Message $message) {
            return $message->getMessage();
        }, $this->getMessages());
    }

    public function getDateUpdated()
    {
        return $this->dateUpdated();
    }

    public function setDateUpdated($time)
    {
        $this->dateUpdated = $time;

        return $this;
    }

    public function getDateCreated()
    {
        return $this->dateUpdated();
    }

    public function setDateCreated($time)
    {
        $this->dateCreated = $time;

        return $this;
    }

    /**
     * Model Hook: After validation on create.
     */
    public function afterValidationOnCreate()
    {
        $time = time();
        $this->setDateCreated($time);
        $this->setDateUpdated($time);
    }

    /**
     * Model Hook: After validation.
     */
    public function afterValidation()
    {
        $time = time();
        $this->setDateUpdated($time);
    }

    /**
     * Aggregate request with pagination logic added at the end.
     * uses _GET ?limit=int and ?page=int params or uses default values.
     *
     * @param array $params Beginning of aggregation pipeline.
     *
     * @return array|false False if failed / array if results with pagination.
     *
     */
    public static function aggregatePagination(array $params = null)
    {
        $pagination = self::getAggregatePagination();
        $result = parent::aggregate(array_merge($params, $pagination))->toArray();
        if (isset($result[0]) && isset($result[0]['records']) && count($result[0]['records']) > 0) {
            $result[0]['pageLimit'] = self::getPageLimitPagination();
            return $result[0];
        }
        return false;
    }

    /**
     * gets pagination logic for aggregate pipeline
     *
     * @return array
     */
    public static function getAggregatePagination()
    {
        // sort out default get parameters
        $request = new Request;
        $page = $request->get('page', null, 1);
        $limit = $request->get('limit', null, self::getDefaultLimit());
        $skip = 0;
        if ($skip !== 0 || $page > 1) {
            $skip = ($skip !== 0) ? $skip : ($page - 1) * $limit;
        }

        // add pagination logic
        $params[] = [
            '$group' =>
                [
                    '_id' => 'null',
                    'many' => ['$sum' => 1],
                    'all' => ['$push' => '$$ROOT']
                ]];
        $params[] = [
            '$project' =>
                [
                    '_id' => 0,
                    'totalRecords' => '$many',
                    'records' => ['$slice' => ['$all', (int)$skip, (int)$limit]],
                ]
        ];
        return $params;
    }

    /**
     * returns page limit from $_GET or default value
     * @return int
     */
    public static function getPageLimitPagination()
    {
        $request = new Request;
        $limit = $request->get('limit', null, self::getDefaultLimit());

        return $limit;
    }
}
