<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\db\ActiveQuery;

/**
 * Created by PhpStorm.
 * User: rem
 * Date: 23.12.2014
 * Time: 17:04
 */
class AjaxData extends Component
{
    const PARAM_START = 'iDisplayStart';
    const PARAM_LIMIT = 'iDisplayLength';
    const PARAM_SORT_COL = 'iSortCol_';
    const PARAM_SORT_COUNT = 'iSortingCols';
    const PARAM_SORTABLE = 'bSortable_';
    const PARAM_SORT_DIR = 'sSortDir_';
    const PARAM_SEARCH = 'sSearch';
    const PARAM_SEARCHABLE = 'bSearchable_';
    const PARAM_SEARCH_NUM = 'sSearch_';
    const PRAM_S_ECHO = 'sEcho';

    const TABLE_BUTTON = 'buttons';

    /**
     * @var \yii\db\ActiveQuery
     */
    private $query;

    /**
     * @var array
     */
    private $params;

    /**
     * @var array|null
     */
    private $additional;

    /**
     * @var int
     */
    private $totalCount;

    /**
     * @var int
     */
    private $filteredCount;

    /**
     * @var int
     */
    private $start;

    /**
     * @var string
     */
    private $order;

    /**
     * @var string
     */
    private $with;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var int
     */
    private $sEcho;

    /**
     * @var string[]|null
     */
    private $search;

    private $where;

    private $_dataAttributes;

    /**
     * @param ActiveQuery $query
     * @param array $params
     * @param array|null $additional
     * @return $this
     */
    public function find(ActiveQuery $query, array $params, $additional = null)
    {
        $this->query = $query;
        $this->params = $params;
        $this->additional = $additional;
        if(isset($params['where'])){
            $this->where = [$params['where']];
            $this->query->where($params['where']);
        }
        $this->totalCount = $query->count();
        $this->query->where = null;
        $this->order = $this->getSort();
        if($this->order === null){
            $this->order = isset($params['orderBy']) ? $params['orderBy'] : null;
        }
        $this->with = isset($params['with']) ? $params['with'] : null;
        $this->limit = $this->getLimit();
        if($this->limit === null){
            $this->limit = isset($params['limit']) ? $params['limit'] : null;
        }

        $this->search = $this->getSearch();

        $this->start = isset($params[self::PARAM_START]) ? $params[self::PARAM_START] : 0;
        $this->sEcho = isset($params[self::PRAM_S_ECHO]) ? $params[self::PRAM_S_ECHO] : 1;

        if($this->search !== null){
            foreach($this->search as $search){
                $this->query->orWhere($search);
            }
        }

        if($this->where !== null){
            foreach($this->where as $cond){
                $this->query->andWhere($cond);
            }
        }

        //Yii::$app->test->show($this->query->createCommand()->sql);

        $this->filteredCount = $query->count();

        if($this->with !== null)
            $this->query->with($this->with);

        if($this->order !== null)
            $this->query->orderBy($this->order);

        if($this->limit !== null)
            $this->query->limit($this->limit);

        if($this->start !== null)
            $this->query->offset($this->start);

        //Yii::$app->test->show([$this->query->createCommand()->sql, $this->query->createCommand()->params]);

        return $this;
    }

    public function all()
    {
        $json = array(
            'sEcho' => $this->sEcho,
            'iTotalRecords' => $this->totalCount,
            'iTotalDisplayRecords' => $this->filteredCount,
            'aaData' => $this->getData(),
        );

        return $json;
    }

    /**
     * return array
     */
    private function getData()
    {
        $data = $this->query->all();
        $res = [];

        if($data && $this->getAttributes()){

            $search = [];

            // Mark found text
            if($this->search){
                foreach($this->search as $param){
                    if(is_array($param)){
                        if(count($param) === 3){
                            $value = $param[2];
                            if($value)
                                $search[$param[1]] = $value;
                        }elseif(count($param) === 1){
                            $names = array_keys($param);
                            $value = $param[$names[0]];
                            if($value)
                                $search[$names[0]] = $value;
                        }
                    }
                }
            }

            foreach($data as $num => $obj){
                $tmpArr = [];

                foreach($this->getAttributes() as $attr){
                    $attrName = $attr['name'];
                    $attrVal = $obj->$attrName;

                    if(!empty($search)){
                        if(isset($attr['search']) && $attr['search']){
                            if(is_array($attr['search'])){
                                $attrName = isset($attr['search']['name']) ? $attr['search']['name'] : $attrName;
                            }elseif(is_string($attr['search'])){
                                $attrName = $attr['search'];
                            }

                            foreach($search as $name => $value){
                                if($attrName === $name){
                                    // Mark the text part
                                    $attrVal = str_replace($value, '<b>'.$value.'</b>', $attrVal);
                                }
                            }
                        }
                    }

                    $tmpArr[] = $attrVal;
                }

                if(!empty($tmpArr))
                    $res[] = $tmpArr;
            }
        }

        return $res;
    }

    /**
     * @return null|string
     */
    private function getSort()
    {
        $by = null;
        $params = $this->params;
        $colName = self::PARAM_SORT_COL;
        $columns = $this->getAttributes();

        if(isset($params[$colName.'0']))
        {
            for($i=0 ; $i < intval($params[self::PARAM_SORT_COUNT]) ; $i++){
                $sortNum = self::PARAM_SORTABLE.intval($params[$colName.$i]);

                if(isset($params[$sortNum]) && $params[$sortNum] == 'true'){
                    $num = intval($params[$colName.$i]);
                    if(isset($columns[$num])){
                        if($columns[$num]['sort'] === false)
                            continue;

                        if($columns[$num]['sort'] === true)
                            $by = $columns[$num]['name'].' '.$params[self::PARAM_SORT_DIR.$i];
                        else
                            $by = $columns[$num]['sort'].' '.$params[self::PARAM_SORT_DIR.$i];
                        break;
                    }
                }
            }
        }

        return $by;
    }

    /**
     * @return int|null
     */
    private function getLimit()
    {
        $limit = null;
        if(isset($this->params[self::PARAM_LIMIT]))
            $limit = (int)$this->params[self::PARAM_LIMIT];
        if($limit === 0)
            $limit = null;
        return $limit;
    }

    /**
     * @return array|null
     *
     * Example search param:
     * 'registrationDate' => [
            'name' => 'registration_date',
            'type' => 'array',
            'value' => Yii::t('base', 'Регистрация'),
            'sort' => 'registration_date',
            'search' => [
                'cond' => 'NOT IN', // 'IN', 'IS', 'IS NOT'
                'val' => [1, 2, 3], // null, 334, 'bla-bla-lba'
            ],
        ],
     *
     * OR:
     * 'email' => [
            'value' => Yii::t('base', 'Email'),
            'sort' => true,
            'search' => 'like',
        ],
     *
     * OR:
     * 'id' => [
            'value' => Yii::t('base', 'ID'),
            'sort' => true,
            'search' => [
                'type' => 'int',
            ],
        ],
     *
     * OR:
     * 'lastLogin' => [
            'value' => Yii::t('base', 'Последнее посещение'),
            'sort' => 'last_login',
        ],
     */
    private function getSearch()
    {
        $s = null;
        $params = $this->params;
        $searchVal = isset($params[self::PARAM_SEARCH]) ? trim($params[self::PARAM_SEARCH]) : null;
        $columns = $this->getAttributes();

        if($searchVal){
            $s = [];

            for($i=0 ; $i < count($columns) ; $i++){
                $condition = $columns[$i]['search'];
                if($condition === false)
                    continue;

                $type = $columns[$i]['type'];

                if(is_array($condition)){
                    if(isset($condition['type']))
                        $type = $condition['type'];

                    if(isset($condition['cond'])){
                        $cond = $condition['cond'];
                        $name = isset($condition['name']) ? $condition['name'] : $columns[$i]['name'];

                        if(isset($condition['val'])){
                            $val = $condition['val'];
                            $searchVal = $val === null ? null : $this->typeValue($type, $val);

                            if($cond === 'IS'){
                                $s[] = [$name => $searchVal];
                                continue;
                            }
                        }else{
                            $searchVal = $this->typeValue($type, $searchVal);
                        }

                        $s[] = [$cond, $name, $searchVal];
                    }else{
                        $name = isset($condition['name']) ? $condition['name'] : $columns[$i]['name'];
                        $s[] = [$name => $this->typeValue($type, $searchVal)];
                    }

                    continue;
                }

                if($condition === true){
                    $s[] = [$columns[$i]['name'] => $this->typeValue($type, $searchVal)];
                    continue;
                }

                if(is_string($condition) && strlen($condition) > 1){
                    $s[] = [$condition, $columns[$i]['name'], $this->typeValue($type, $searchVal)];
                    continue;
                }
            }
        }

        return $s;

        /**
         * Individual filtering
         *
            $searchable = self::PARAM_SEARCHABLE;
            $search = self::PARAM_SEARCH_NUM;
            $filters = '';

            for($i=0; $i<count($columns); $i++){
                if(isset($params[$searchable.$i]) && $params[$searchable.$i] == 'true' && isset($params[$search.$i]) && $params[$search.$i]){
                    $name = $columns[$i]->search;
                    $value = $params[$search.$i];

                    list($cond, $attr) = $this->addAttribute($name, $value, $columns[$i]->type, ':filter_'.$i);

                    if($cond){
                        $filters .= $cond.' AND ';
                        $attributes = array_merge($attributes, $attr);
                    }
                }
            }
         */
    }

    /**
     * @return array
     */
    private function getAttributes()
    {
        if($this->_dataAttributes === null){
            $className = $this->query->modelClass ? $this->query->modelClass : $this->query->primaryModel;
            $this->_dataAttributes = $className::dataAttributes($this->additional);
        }

        return $this->_dataAttributes;
    }

    private function typeValue($type, $value)
    {
        switch($type){
            case 'string':
                $value = (string)$value;
                break;
            case 'int':
                $value = (int)$value;
                break;
            case 'array':
                $value = (array)$value;
        }

        return $value;
    }
}
