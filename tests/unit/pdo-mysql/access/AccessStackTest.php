<?php
namespace itbz\datamapper\pdo\access;

use itbz\datamapper\pdo\Search;
use itbz\datamapper\tests\Model;

class AccesStackTest extends \itbz\datamapper\MysqlTestCase
{
    public function setUp()
    {
        $pdo = parent::setUp();
        $format = "INSERT INTO data (name, data, owner, `group`, mode) VALUES ('%s', '%s', '%s', '%s', %s)";
        $pdo->query(sprintf($format, 'useronly', 'test', 'usr', 'grp', '448'));
        $pdo->query(sprintf($format, 'grponly', 'test', 'usr', 'grp', '56'));
        $pdo->query(sprintf($format, 'usrgrp', 'test', 'usr', 'grp', '504'));
    }

    public function getMapper()
    {
        $table = new AcTable('data', $this->getPdo(), '', '', 0777);
        $mapper = new AcMapper($table, new Model());
        return $mapper;
    }

    public function testFindMany()
    {
        $mapper = $this->getMapper();

        // 'usr' should find rows 'useronly' and 'usrgrp'
        $mapper->setUser('usr', array('foo', 'bar'));

        $iterator = $mapper->findMany(array(), new Search());
        $found = '';
        foreach ($iterator as $key => $data) {
            $found .= $key . ' ';
        }
        $this->assertEquals('useronly usrgrp ', $found);
    }

    /**
     * @expectedException itbz\datamapper\pdo\access\AccessDeniedException
     */
    public function testRowAccessException()
    {
        // Unnamed user is blocked
        $mapper = $this->getMapper();
        $mapper->findMany(array(), new Search());
    }

    public function testDelete()
    {
        $mapper = $this->getMapper();
        $mapper->setUser('usr', array('foo', 'bar'));
        $mapper->delete(new Model());

        $where = array('name' => "useronly");
        $iterator = $mapper->findMany($where, new Search());
        $count = 0;
        foreach ($iterator as $key => $data) {
            $count++;
        }
        $this->assertEquals(0, $count, 'useronly should be deleted');

        $mapper->setUser('', array('grp'));

        $iterator = $mapper->findMany(array(), new Search());
        $count = 0;
        foreach ($iterator as $key => $data) {
            $count++;
        }
        $this->assertEquals(1, $count, 'grp can read grponly');
    }

    /**
     * @expectedException itbz\datamapper\pdo\access\AccessDeniedException
     */
    public function testRowDeleteException()
    {
        $mapper = $this->getMapper();
        $mapper->delete(new Model());
    }

    public function testInsert()
    {
        $mapper = $this->getMapper();
        $mapper->setUser('foo', array('bar'));

        $model = new Model();
        $model->name = 'foobar';
        $mapper->save($model);

        $fromDb = $mapper->findByPk('foobar');
        $this->assertEquals('foo', $fromDb->owner);
        $this->assertEquals('bar', $fromDb->group);
    }

    public function testUpdate()
    {
        $mapper = $this->getMapper();
        $mapper->setUser('usr', array('foo', 'bar'));

        $model = new Model();
        $model->name = "useronly";
        $model->data = "updated";
        $mapper->save($model);

        $fromDb = $mapper->findByPk('useronly');
        $this->assertEquals('updated', $fromDb->data);
    }

    /**
     * @expectedException itbz\datamapper\pdo\access\AccessDeniedException
     */
    public function testRowUpdateException()
    {
        $mapper = $this->getMapper();
        $mapper->setUser('usr', array('grp'));

        $model = new Model();
        $model->name = "foobar";
        $model->mode = 04;
        $mapper->save($model);

        $mapper->setUser('');
        $update = new Model();
        $update->name = "foobar";
        $update->data = "updated";
        $mapper->save($update);
    }

    public function testChown()
    {
        $mapper = $this->getMapper();
        $mapper->setUser('root');

        $model = new Model();
        $model->name = "useronly";
        $mapper->chown($model, 'foobar');

        $mapper->setUser('foobar');

        // Now only useronly, with owner foobar, should be readable
        $iterator = $mapper->findMany(array(), new Search());
        $found = '';
        foreach ($iterator as $key => $data) {
            $found .= $key . ' ';
        }
        $this->assertEquals('useronly ', $found);
    }

    public function testChmod()
    {
        $mapper = $this->getMapper();
        $mapper->setUser('non-root');

        $model = new Model();
        $model->name = "useronly";

        // Chmod does nothing as current user does not own
        $nRows = $mapper->chmod($model, 0777);
        $this->assertEquals(0, $nRows);

        // But owner can change
        $mapper->setUser('usr');
        $nRows = $mapper->chmod($model, 0777);
        $this->assertEquals(1, $nRows);

        // And so can root
        $mapper->setUser('root');
        $nRows = $mapper->chmod($model, 0770);
        $this->assertEquals(1, $nRows);
    }

    public function testChgrp()
    {
        $mapper = $this->getMapper();
        $mapper->setUser('non-root', array('newgroup'));

        $model = new Model();
        $model->name = "useronly";

        // Chmod does nothing as current user does not own
        $nRows = $mapper->chgrp($model, 'newgroup');
        $this->assertEquals(0, $nRows);

        // But owner can change
        $mapper->setUser('usr', array('newgroup'));
        $nRows = $mapper->chgrp($model, 'newgroup');
        $this->assertEquals(1, $nRows);

        // And so can root
        $mapper->setUser('root');
        $nRows = $mapper->chgrp($model, 'root');
        $this->assertEquals(1, $nRows);
    }
}
