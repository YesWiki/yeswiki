<?php

namespace YesWiki\AutoUpdate\Entity;

class Collection implements \ArrayAccess, \Iterator, \Countable
{
    protected $list = [];

    public function toArray()
    {
        return $this->list;
    }

    /***************************************************************************
     * ArrayAccess
     **************************************************************************/
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->list[] = $value;

            return;
        }
        $this->list[$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->list[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->list[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return isset($this->list[$offset]) ? $this->list[$offset] : null;
    }

    /***************************************************************************
     * Iterator
     **************************************************************************/
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        return reset($this->list);
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->list);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->list);
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return isset($this->list[$this->key()]);
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        return next($this->list);
    }

    /*************************************************************************
     * Countable
     ************************************************************************/
    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->list);
    }
}
