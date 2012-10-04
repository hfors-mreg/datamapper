<?php
namespace itbz\datamapper\pdo;

use pdo;

class IteratorTest extends \PHPUnit_Framework_TestCase
{
    public function getStmt()
    {
        $pdo = new pdo('sqlite::memory:');
        $pdo->setAttribute(pdo::ATTR_ERRMODE, pdo::ERRMODE_EXCEPTION);
        $pdo->query('CREATE TABLE data(id INTEGER, name, PRIMARY KEY(id ASC));');
        $pdo->query("INSERT INTO data VALUES (1, 'foo')");
        $pdo->query("INSERT INTO data VALUES (2, 'bar')");

        return $pdo->query('SELECT * FROM data');
    }

    public function testIterator()
    {
        $model = $this->getMock(
            '\itbz\datamapper\ModelInterface',
            array('load', 'extract')
        );

        $model->expects($this->exactly(4))
              ->method('load');

        $model->expects($this->at(0))
              ->method('load')
              ->with(array('id' => '1', 'name' => 'foo'));

        $model->expects($this->at(1))
              ->method('load')
              ->with(array('id' => '2', 'name' => 'bar'));

        $model->expects($this->at(2))
              ->method('load')
              ->with(array('id' => '1', 'name' => 'foo'));

        $model->expects($this->at(3))
              ->method('load')
              ->with(array('id' => '2', 'name' => 'bar'));

        $iterator = new Iterator($this->getStmt(), 'id', $model);

        // Iterating over iterator yields two calls to model::load()
        foreach ($iterator as $key => $model) {
            $this->assertInstanceOf('itbz\datamapper\ModelInterface', $model);
            $this->assertTrue(is_numeric($key));
        }

        // Re-iterating yields two more..
        foreach ($iterator as $key => $model) {
        }
    }
}
