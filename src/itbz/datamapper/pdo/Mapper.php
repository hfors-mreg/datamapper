<?php
/**
 * This file is part of the datamapper package
 *
 * Copyright (c) 2012 Hannes Forsgård
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Hannes Forsgård <hannes.forsgard@gmail.com>
 * @package datamapper\pdo
 */

namespace itbz\datamapper\pdo;

use itbz\datamapper\MapperInterface;
use itbz\datamapper\ModelInterface;
use itbz\datamapper\IgnoreAttributeInterface;
use itbz\datamapper\SearchInterface;
use itbz\datamapper\exception\DataNotFoundException;
use itbz\datamapper\Exception;
use itbz\datamapper\pdo\table\Table;
use pdoStatement;

/**
 * pdo mapper object
 *
 * @package datamapper\pdo
 */
class Mapper implements MapperInterface
{
    /**
     * Table object to interact with
     *
     * @var Table
     */
    protected $table;

    /**
     * Prototype model that will be cloned on object creation
     *
     * @var ModelInterface
     */
    private $prototype;

    /**
     * Construct and inject table instance
     *
     * @param Table $table
     * @param ModelInterface $prototype Prototype model that will be cloned when
     * mapper needs a new return object.
     */
    public function __construct(Table $table, ModelInterface $prototype)
    {
        $this->table = $table;
        $this->prototype = $prototype;
    }

    /**
     * Get iterator containing multiple racords based on search
     *
     * @param array $conditions
     * @param SearchInterface $search
     *
     * @return \Iterator
     */
    public function findMany(array $conditions, SearchInterface $search)
    {
        $stmt = $this->table->select(
            $search,
            $this->arrayToExprSet($conditions)
        );

        return $this->getIterator($stmt);
    }

    /**
     * Find models that match current model values.
     *
     * @param array $conditions
     *
     * @return ModelInterface
     *
     * @throws DataNotFoundException if no model was found
     */
    public function find(array $conditions)
    {
        $search = new Search();
        $search->setLimit(1);
        $iterator = $this->findMany($conditions, $search);

        // Return first object in iterator
        foreach ($iterator as $object) {

            return $object;
        }

        // This only happens if iterator is empty
        throw new DataNotFoundException("No matching records found");
    }

    /**
     * Find model based on primary key
     *
     * @param mixed $key
     *
     * @return ModelInterface
     */
    public function findByPk($key)
    {
        return $this->find(
            array($this->table->getPrimaryKey() => $key)
        );
    }

    /**
     * Delete model from persistent storage
     *
     * @param ModelInterface $model
     *
     * @return int Number of affected rows
     */
    public function delete(ModelInterface $model)
    {
        $pk = $this->table->getPrimaryKey();
        $conditions = $this->extractForDelete($model, array($pk));
        $stmt = $this->table->delete($conditions);

        return $stmt->rowCount();
    }

    /**
     * Persistently store model
     *
     * If model contains a primary key and that key exists in the database
     * model is updated. Else model is inserted.
     *
     * @param ModelInterface $model
     *
     * @return int Number of affected rows
     */
    public function save(ModelInterface $model)
    {
        try {
            $pk = $this->getPk($model);
            if ($pk && $this->findByPk($pk)) {
                // Model has a primary key and that key exists in db

                return $this->update($model);
            }
        } catch (DataNotFoundException $e) {
            // Do nothing, exception triggers insert, as do models with no PK
        }

        return $this->insert($model);
    }

    /**
     * Get the ID of the last inserted row.
     *
     * The return value will only be meaningful on tables with an auto-increment
     * field and with a pdo driver that supports auto-increment. Must be called
     * directly after an insert.
     *
     * @return int
     */
    public function getLastInsertId()
    {
        return $this->table->lastInsertId();
    }

    /**
     * Get primary key from model
     *
     * @param ModelInterface $model
     *
     * @return string Empty string if no key was found
     */
    public function getPk(ModelInterface $model)
    {
        $pk = $this->table->getPrimaryKey();
        $exprSet = $this->extractForRead($model, array($pk));

        if ($exprSet->isExpression($pk)) {

            return $exprSet->getExpression($pk)->getValue();
        }

        return '';
    }

    /**
     * Get a new prototype clone
     *
     * @return ModelInterface
     */
    public function getNewModel()
    {
        return clone $this->prototype;
    }

    /**
     * Insert model into db
     *
     * @param ModelInterface $model
     *
     * @return int Number of affected rows
     */
    protected function insert(ModelInterface $model)
    {
        $data = $this->extractForCreate($model);
        $stmt = $this->table->insert($data);

        return $stmt->rowCount();
    }

    /**
     * Update db using primary key as conditions clause.
     *
     * @param ModelInterface $model
     *
     * @return int Number of affected rows
     */
    protected function update(ModelInterface $model)
    {
        $data = $this->extractForUpdate($model);
        $pk = $this->table->getPrimaryKey();
        $conditions = $this->extractForRead($model, array($pk));
        $stmt = $this->table->update($data, $conditions);

        return $stmt->rowCount();
    }

    /**
     * Get iterator for pdoStatement
     *
     * @param pdoStatement $stmt
     *
     * @return \Iterator
     */
    protected function getIterator(pdoStatement $stmt)
    {
        return new Iterator(
            $stmt,
            $this->table->getPrimaryKey(),
            $this->prototype
        );
    }

    /**
     * Extract data from model
     *
     * This method should not be called directly. Use one of 'extractForCreate',
     * 'extractForRead', 'extractForUpdate' or 'extractForDelete' instead.
     *
     * @param ModelInterface $model
     * @param int $context Extract context
     * @param array $use List of model properties to extract. Defaults to table
     * native columns.
     *
     * @return array
     *
     * @throws Exception if extract context is invalid
     * @throws Exception if model extract does not return an array
     */
    protected function extractArray(
        ModelInterface $model,
        $context,
        array $use = null
    ) {
        // @codeCoverageIgnoreStart
        if (!$context) {
            $msg = "Invalid extract context '$context'";
            throw new Exception($msg);
        }
        // @codeCoverageIgnoreEnd

        if (!$use) {
            $use = $this->table->getNativeColumns();
        }

        $data = $model->extract($context, $use);

        if (!is_array($data)) {
            $type = gettype($data);
            $msg = "Model extract must return an array, found '$type'";
            throw new Exception($msg);
        }

        $data = array_intersect_key($data, array_flip($use));

        return $data;
    }

    /**
     * Extract data from model for data inserts
     *
     * @param ModelInterface $mod
     * @param array $use
     *
     * @return ExpressionSet
     */
    protected function extractForCreate(ModelInterface $mod, array $use = null)
    {
        return $this->arrayToExprSet(
            $this->extractArray($mod, self::CONTEXT_CREATE, $use)
        );
    }

    /**
     * Extract data from model for data read
     *
     * @param ModelInterface $mod
     * @param array $use
     *
     * @return ExpressionSet
     */
    protected function extractForRead(ModelInterface $mod, array $use = null)
    {
        return $this->arrayToExprSet(
            $this->extractArray($mod, self::CONTEXT_READ, $use)
        );
    }

    /**
     * Extract data from model for data updates
     *
     * @param ModelInterface $mod
     * @param array $use
     *
     * @return ExpressionSet
     */
    protected function extractForUpdate(ModelInterface $mod, array $use = null)
    {
        return $this->arrayToExprSet(
            $this->extractArray($mod, self::CONTEXT_UPDATE, $use)
        );
    }

    /**
     * Extract data from model for data deletes
     *
     * @param ModelInterface $mod
     * @param array $use
     *
     * @return ExpressionSet
     */
    protected function extractForDelete(ModelInterface $mod, array $use = null)
    {
        return $this->arrayToExprSet(
            $this->extractArray($mod, self::CONTEXT_DELETE, $use)
        );
    }

    /**
     * Convert array to ExpressionSet
     *
     * @param array $data
     *
     * @return ExpressionSet
     */
    protected function arrayToExprSet(array $data)
    {
        $exprSet = new ExpressionSet();
        foreach ($data as $name => $expr) {
            if (!$expr instanceof IgnoreAttributeInterface) {
                if (!$expr instanceof Expression) {
                    $expr = new Expression($name, $expr);
                }
                $exprSet->addExpression($expr);
            }
        }

        return $exprSet;
    }
}
