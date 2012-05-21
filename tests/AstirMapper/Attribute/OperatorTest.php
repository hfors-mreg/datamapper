<?php
namespace itbz\Astir;

class OperatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test toSearchSql()
     */
    public function testToSearchSql()
    {
        $op = new Operator('<', 'val');
        $sql = $op->toSearchSql($context);
        $this->assertEquals($sql, 'val');
        $this->assertEquals($context, ':name: < ?');
    }


    /**
     * Test toInsertSql()
     */
    public function testToInsertSql()
    {
        $op = new Operator('=', 'val');
        $sql = $op->toInsertSql($use);
        $this->assertTrue(!$use);
    }


    /**
     * Test invalid operator
     * @expectedException InvalidArgumentException
     */
    public function testOperatorFail()
    {
        $op = new Operator('sdfsdf', 'val');
    }


    /**
     * Test supported operators
     * @dataProvider operatorProvider
     */ 
    public function testOperators($operator, $inverted)
    {
        $op = new Operator($operator, 'val');
        $sql = $op->toSearchSql($context);
        $this->assertEquals($context, ":name: $operator ?");
    }


    /**
     * Test operator inversion
     * @dataProvider operatorProvider
     */
    public function testInversion($operator, $inverted)
    {
        $op = new Operator($operator, 'val');
        $sql = $op->invert()->toSearchSql($context);
        $this->assertEquals($context, ":name: $inverted ?");
    }


    /**
     * Provide the list of valid operators and their inversions
     */
    public function operatorProvider()
    {
        return array(
            array('<=>', '!='),
            array('=', '!='),
            array('>=', '<'),
            array('>', '<='),
            array('IS NOT', 'IS'),
            array('IS', 'IS NOT'),
            array('<=', '>'),
            array('<', '>='),
            array('LIKE', 'NOT LIKE'),
            array('!=', '='),
            array('<>', '='),
            array('NOT LIKE', 'LIKE'),
            array('NOT REGEXP', 'REGEXP'),
            array('REGEXP', 'NOT REGEXP'),
            array('RLIKE', 'NOT REGEXP'),
            array('SOUNDS LIKE', 'SOUNDS LIKE'),
        );
    }

}