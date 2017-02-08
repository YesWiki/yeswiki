<?php
namespace AutoUpdate;

class Collection implements \ArrayAccess, \Iterator, \Countable
{
    protected $list = array();

    /***************************************************************************
     * ArrayAccess
     **************************************************************************/
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->list[] = $value;
            return;
        }
        $this->list[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->list[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->list[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->list[$offset]) ? $this->list[$offset] : null;
    }

    /***************************************************************************
     * Iterator
     **************************************************************************/
    public function rewind()
    {
        return reset($this->list);
    }

    public function current()
    {
        return current($this->list);
    }

    public function key()
    {
        return key($this->list);
    }

    public function valid()
    {
        return isset($this->list[$this->key()]);
    }

    public function next()
    {
        return next($this->list);
    }

    /*************************************************************************
     * Countable
     ************************************************************************/
    public function count()
    {
        return count($this->list);
    }
}
