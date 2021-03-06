<?php

/**
 * @author ryan
 */

// get_called_class() can be replaced by static::

class Searcher
{
    const KEY_VALUE_PAIR = 1;

    private $table = null;
    private $class = null;
    private $tables = array();
    private $conds = array();
    private $orders = array();
    private $limit = 1000;
    private $offset = 0;
    private $distinct = false;
    
    public function __construct($class)
    {
        $this->class = $class;
        $this->table = $class::table();
        $this->tables[] = $this->table;
    }

    public function table()
    {
        return $this->table;
    }

    public function filterBy($exp, $value, $cmp = '=')
    {
        $cmp = " $cmp ";

        // is_object() 判断不可少，不然SAE上会把String也认为Ojbect
        if (is_object($value) && is_a($value, 'BasicModel'))
            $value = $value->id;

        $relationMap = $this->relationMap();
        $tableDotKey = preg_match('/\b(\w+)\.(\w+)\b/', $exp, $matches); // table.key = ?
        $tableDotId = isset($relationMap[$exp]);

        if ($tableDotKey) {
            $ref = $matches[1];
            $refKey = $matches[2];
            $refTable = $relationMap[$ref];
            $this->conds["$refTable.$refKey".$cmp.'?'] = $value;
            $this->conds["$this->table.$ref".$cmp."$refTable.id"] = null;
        } else {
            if (strpos($exp, '?') === false && $value !== null) {
                $exp = "$this->table.$exp".$cmp.'?';
            }
            $this->conds[$exp] = $value;
        }
            
        return $this;
    }

    public function orderBy($exp)
    {
        $this->orders[] = "$this->table.$exp";
        return $this;
    }

    public function limit()
    {
        if (!func_num_args())
            return $this->limit;
        $this->limit = func_get_arg(0);
        return $this;
    }

    public function offset()
    {
        if (!func_num_args())
            return $this->offset;
        $this->offset = func_get_arg(0);
        return $this;
    }

    public function join(Searcher $s)
    {
        $st = $s->table();
        $rMap = array_flip($s->relationMap());
        $refKey = $rMap[$this->table];
        $this->conds[$st . ".$refKey=$this->table.id"] = null;
        $this->conds += $s->conds;

        if (!in_array($st, $this->tables))
            $this->tables[] = $st;
        return $this;
    }

    public function distinct()
    {
        $this->distinct = true;
        return $this;
    }

    public function find($form = 0)
    {
        $field = "$this->table.id";
        if ($this->distinct)
            $field = "DISTINCT($field)";
        $limitStr = $this->limit ? "LIMIT $this->limit" : '';
        $tail = "$limitStr OFFSET $this->offset";
        if ($this->conds) {
            $condStr = implode(' AND ', array_keys($this->conds));
            $a = array_filter(array_values($this->conds), function ($v) {return $v!==false && $v!==null;});
            $values = array();
            foreach ($a as $v) {
                if (is_array($v)) {
                    $values += $v;
                } else {
                    $values[] = $v;
                }
            }
            $conds = array($condStr => $values);
        } else {
            $conds = '';
        }
        $ids = Sdb::fetch($field, $this->tables, $conds, $this->orders, $tail);

        $class = $this->class;
        $ret = array_map(function ($id) use($class) {
            return new $class($id);
        }, $ids);
        if ($form) {
            if ($form === self::KEY_VALUE_PAIR) {
                $kvs = array();
                foreach ($ret as $o) {
                    $kvs[$o->id] = $o->value();
                }
                return $kvs;
            }
        }
        return $ret;
    }

    public function count()
    {
        $field = count($this->tables) > 1 ? "$this->table.id" : '*';
        if ($this->distinct)
            $field = "DISTINCT($field)";
        $field = "count($field)";
        if ($this->conds) {
            $condStr = implode(' AND ', array_keys($this->conds));
            $a = array_filter(array_values($this->conds), function ($v) {return $v!==false && $v!==null;});
            $values = array();
            foreach ($a as $v) {
                if (is_array($v)) {
                    $values += $v;
                } else {
                    $values[] = $v;
                }
            }
            $conds = array($condStr => $values);
        } else {
            $conds = '';
        }
        $arr = Sdb::fetch($field, $this->tables, $conds);
        return $arr[0];
    }

    // ------------ private section -----------------

    private function relationMap()
    {
        if (isset($this->relationMap))
            return $this->relationMap;
        $class = $this->class;
        return $this->relationMap = $class::relationMap();
    }
}
