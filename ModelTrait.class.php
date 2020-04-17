<?php


Trait ModelTrait
{
    public static $app;

    public static $instance = null;

    public static $attrData = [];

    protected $orm = [];

    protected $attrMethods = [];

    protected $ormKey;

    public function setOrmKey($ormKey)
    {
        $this->ormKey = $ormKey;
        return $this;
    }

    public function once($key, \Closure $callback = null)
    {
        if (isset(self::$attrData[$key])) return self::$attrData[$key];
        if ($callback) return self::$attrData[$key] = $callback();
    }

    public static function __callStatic($name, $arguments)
    {
        $instance = self::$instance = self::$instance ? self::$instance : new static;
        $method   = substr($name, 1);
        $before   = 'before' . $name;
        $after    = 'after' . $name;

        method_exists($instance, $before) && call_user_func_array([clone $instance, $before], $arguments);
        $res = call_user_func_array([$instance, $method], $arguments);
        method_exists($instance, $after) && call_user_func_array([clone $instance, $after], $arguments);

        return $res;
    }

    protected function this()
    {
        return $this;
    }

    private function _after_sql_fetch(&$result, $options)
    {
        $model = (new static(self::$app))->data($result);
        $this->orm[] = $model;
        $model->setOrm($this->orm);
    }

    private function _after_sql_exec(&$result, $options)
    {
        $this->orm = [];
        $this->data($result);
        $this->_after_sql_fetch($result, $options);
    }

    protected function _after_find(&$result, $options)
    {
        $this->_after_sql_fetch($result, $options);
    }

    protected function _after_insert(&$data, $options)
    {
        $this->_after_sql_exec($data, $options);
    }

    protected function _after_update(&$data, $options)
    {
        $this->_after_sql_exec($data, $options);
    }

    protected function _after_select(&$results, $options)
    {
        if (!$results) return;

        foreach ($results as $result) {
            $this->_after_sql_fetch($result, $options);
        }
    }

    public function setOrm(&$orm)
    {
        $this->orm = &$orm;
        return $this;
    }

    public function getOrm()
    {
        return count($this->orm) === 1 ? $this->orm[0] : $this->orm;
    }

    private function getAllFields($field = null)
    {
        if ($field === null) {
            return $this->getDbFields();
        } else if (is_string($field)) {
            return [$field];
        } else if (is_array($field)) {
            return $field;
        }
    }

    public function setAttrFun($func)
    {
        if (method_exists($this, $func)) {
            $this->attrMethods[] = $func;
        }
        return $this;
    }

    private function getAttrFun($field = null)
    {
        if (!$this->orm) return $this->attrMethods;

        $fields = $this->getAllFields($field);

        foreach ($fields as $field) {
            $method = 'get' . parse_name($field, 1) . 'Attr';
            method_exists($this->orm[0], $method) && array_unshift($this->attrMethods, $method);
        }

        return $this;
    }

    public function doSelect($options = [])
    {
        if ($this->options) $this->select($options);
        return $this;
    }

    public function doFind($options = [])
    {
        $this->orm || $this->find($options);
        return $this;
    }

    public function autoChange($field = null)
    {
        $func = $field === null ? 'getAttrFun' : 'setAttrFun';

        $this->doSelect()->{$func}($field);

        if (!$this->attrMethods) return $this;

        foreach ($this->orm as $key => $orm) {
            $orm->setOrmKey($key);
            foreach ($this->attrMethods as $method) {
                $orm->{$method}();
            }
        }

        return $this;
    }

    public function toData($value = null, $key = null)
    {
        $this->doSelect();

        if (!$this->orm) return [];

        foreach ($this->orm as $orm) {
            $data[] = $orm->data();
        }

        if ($key) return array_column($data, $value, $key);

        if ($value) return array_column($data, $value);

        return $data;
    }

    public function toModelData($key)
    {
        $this->doSelect();

        if (!$this->orm) return [];

        foreach ($this->orm as $orm) {
            $data[$orm->$key] = $orm;
        }

        return $data;
    }

    public function toModelDatas($key)
    {
        $this->doSelect();

        if (!$this->orm) return [];

        foreach ($this->orm as $orm) {
            $data[$orm->$key][] = $orm;
        }

        return $data;
    }

    public function autoData($field = null)
    {
        return $this->autoChange($field)->toData();
    }

    public function hasOne($model, $modelField, $field, \Closure $func = null)
    {
        $data = $this->once($model, function () use ($model, $modelField, $field, $func) {
            if (!$ids = array_filter($this->toData($field))) return [];
            $relation = (new $model)->where([$modelField => ['in', $ids]]);
            $func && $func($relation);
            return $relation->toModelData($modelField);
        });

        if (!isset($data[$this->$field])) return new $model;

        return $data[$this->$field];
    }

    public function hasMany($model, $modelField, $field, \Closure $func = null)
    {
        $data = $this->once($model, function () use ($model, $modelField, $field, $func) {
            if (!$ids = array_filter($this->toData($field))) return [];
            $relation = (new $model)->where([$modelField => ['in', $ids]]);
            $func && $func($relation);
            return $relation->toModelDatas($modelField);
        });

        if (!isset($data[$this->$field])) return new $model;

        return (new $model)->setOrm($data[$this->$field]);
    }


}
