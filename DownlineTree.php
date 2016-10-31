<?php

/**
 * Created by PhpStorm.
 * User: Allan McNaughton
 * Date: 3/10/2010
 * Time: 8:55 PM
 */
class DownlineTree
{
    const MAX_LEVELS = 20;
    const NO_DATE = '0000-00-00 00:00:00';

    public $gridData;

    protected $root;
    protected $model;
    protected $consultantsData;
    protected $consultantsIds;
    protected $tree;

    protected $startDate;
    protected $endDate;
    protected $search;

    /**
     * @param $model
     * @param $month
     * @param $year
     */
    public function __construct($model, $month, $year, $search = null)
    {
        $this->model = $model;
        $this->search = $search;
        $this->root = $model->Session->read('consultant_id');
        if(empty($this->root))
            return;

        // load raw data
        $this->loadData($month, $year);

        // convert raw data to tree structure
        $this->tree = $this->mapTree($this->consultantsData);

        // find appropriate root
        if(!empty($this->root) && $this->root != 1)
            $this->rootTree($this->tree);

        // prune to only matching name plus children
        if(!empty($search))
            $this->pruneTree($this->tree);

        // add levels, group volumes, etc
        $this->annotateTree($this->tree);
    }


    /**
     * Convert flat array to tree
     **
     * @param $dataset
     * @return array
     */
    protected function mapTree($dataset)
    {
        $tree = array();

        foreach ($dataset as $id => &$node) {
            if ($node['parent_id'] == 0) {
                $tree[$id] = &$node;
            } else {
                if (!isset($dataset[$node['parent_id']]['children']))
                    $dataset[$node['parent_id']]['children'] = array();

                $dataset[$node['parent_id']]['children'][$id] = &$node;
            }
        }

        return $tree;
    }

    /**
     * Add levels and group volumes to tree
     *
     * @param $nodes
     * @param int $level
     * @return int|null
     */
    protected function annotateTree(&$nodes, $level = 0)
    {
        if ($level >= self::MAX_LEVELS) return null;

        $group_fv = 0;
        foreach ($nodes as $key => $node) {

            $nodes[$key]['level'] = $level;
            $nodes[$key]['inactive'] = $this->inactiveCheck($node);
            $nodes[$key]['group_fv'] += $node['fv'];
            $group_fv += $node['fv'];

            if (count($node['children'])) {
                $result = $this->annotateTree($nodes[$key]['children'], $level + 1);
                $nodes[$key]['group_fv'] += $result;
                $group_fv += $result;
            }
        }

        return $group_fv;
    }

    /**
     * Tree starts with currently logged consultant_id
     *
     * @param $nodes
     */
    protected function rootTree(&$nodes)
    {
        foreach ($nodes as $node) {
            if($node['consultant_id'] == $this->root) {
                $this->tree = array();
                $this->tree[$node['consultant_id']] = (array)$node;
            }

            if (count($node['children']))
                $this->rootTree($node['children']);
        }
    }

    /**
     * Prune by consultant name within tree
     *
     * @param $nodes
     */
    protected function pruneTree(&$nodes)
    {
        foreach ($nodes as $node) {
            if(stripos($node['name'], $this->search) !== FALSE) {
                $this->tree = array();
                $this->tree[$node['consultant_id']] = (array)$node;
            }

            if (count($node['children']))
                $this->pruneTree($node['children']);
        }
    }

    /**
     * Generate data for jqGrid
     *
     * @return stdClass
     */
    public function getGridData()
    {
        $rows = array();
        $this->createGridData($this->tree, $rows);

        $response = new stdClass();
        $response->page = 1;
        $response->total = 1;
        $response->records = count($rows);
        $response->rows = $rows;

        return $response;
    }

    /**
     * Prep data for jgGrid rendering
     *
     * @param $nodes
     * @param $rows
     */
    protected function createGridData(&$nodes, &$rows)
    {
        if ($indent >= self::MAX_LEVELS) return;

        $prefix .= '<img src="/directsale/webroot/img/smallarrowright.png" class="right-arrow"/>';

        foreach ($nodes as $node) {
            if ($node['inactive'] == 1) continue;

            $data = array();
            array_push($data, $this->indentLevel($node['level']) . $prefix . $node['level']);
            array_push($data, $this->formatName($node));
            array_push($data, $node['cid']);
            array_push($data, $this->formatRank($node));
            array_push($data, $node['last_order']);
            array_push($data, $node['fv']);
            array_push($data, $node['group_fv']);

            array_push($rows,
                array('id' => $node['consultant_id'], 'cell' => $data));

            if (count($node['children']))
                $this->createGridData($node['children'], $rows);
        }
    }

    /**
     * Indent level indicator
     *
     * @param $level
     * @return string
     */
    private function indentLevel($level)
    {
        for ($i = 0; $i < ($level - 1); $i++) {
            $prefix .= '&nbsp;&nbsp;';
        }

        return $prefix;
    }

    /**
     * Determine if consultant should be displayed or not
     *
     * @param $node
     * @return bool
     */
    private function inactiveCheck(&$node)
    {
        $cutoffTime = strtotime('-18 month', strtotime($this->endDate));

        if ($node['last_visit'] == self::NO_DATE && (strtotime($node['register_date']) < $cutoffTime))
            return true;
        else
            if ($node['last_visit'] != self::NO_DATE  && strtotime($node['last_visit']) < $cutoffTime)
                return true;

        return false;
    }

    /**
     * Color code ocnsultant name
     *
     * @param $node
     * @return string
     */
    protected function formatName(&$node)
    {
        $today = time();
        if ($today >= strtotime($node['fsr1_begdate']) && $today < endOfDayTimestamp($node['fsr1_enddate']))
            $color = "red";
        else
            if ($today >= strtotime($node['fsr2_begdate']) && $today < endOfDayTimestamp($node['fsr2_enddate']))
                $color = "orange";
            else
                if ($today >= strtotime($node['fsr3_begdate']) && $today < endOfDayTimestamp($node['fsr3_enddate']))
                    $color = "green";
                else
                    $color = "black";

        $details = "<img src='/directsale/webroot/img/info.png' title='Click to view Activity Details' class='details-link' alt='Click to view Activity Details'>";

        $name = "<span class='black'>{$node['name']}</span>";
        if (empty($node['block']))
            return $details . $name;
        else
            return $details . "<del>$name</del>";
    }

    /**
     * Lookup consultant rank
     *
     * @param $node
     * @return mixed
     */
    protected function formatRank(&$node)
    {
        return $this->model->rank[$node['rank']];
    }

    /**
     * Extract everything needed from the database
     *
     * @param $month
     * @param $year
     */
    protected function loadData($month, $year)
    {
        $this->startDate = $startdate = $year . "-" . $month . "-" . "01";
        $this->endDate = $enddate = $year . "-" . $month . "-" . date('t', strtotime($month . '/01/' . $year));

        // remainder of loadData code removed due to client confidentiality
    }

    /**
     * Dump tree for debug
     *
     * @param $nodes
     * @param int $indent
     */
    public function displayTree(&$nodes, $indent = 1)
    {
        if ($indent >= self::MAX_LEVELS) return;

        foreach ($nodes as $node) {
            print str_repeat('&nbsp;', $indent * 8);
            print $this->formatName($node) . " ({$node['level']})" . " {$node['inactive']}  FV = {$node['fv']}";
            if ($node['group_fv'] != $node['fv'])
                print "     GROUP FV = {$node['group_fv']}";
            print '<br/>';

            if (count($node['children']))
                $this->displayTree($node['children'], $indent + 1);
        }
    }
}
