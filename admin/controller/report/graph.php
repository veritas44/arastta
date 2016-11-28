<?php
/**
 * @package        Arastta eCommerce
 * @copyright      Copyright (C) 2015-2016 Arastta Association. All rights reserved. (arastta.org)
 * @credits        See CREDITS.txt for credits and other copyright notices.
 * @license        GNU General Public License version 3; see LICENSE.txt
 */

class ControllerReportGraph extends Controller
{
    public function index($graph)
    {
        $this->document->addStyle('view/javascript/jquery/daterangepicker/daterangepicker-bs3.css');
        $this->document->addScript('view/javascript/jquery/daterangepicker/daterangepicker.js');
        $this->document->addScript('view/javascript/jquery/flot/jquery.flot.js');
        $this->document->addScript('view/javascript/jquery/flot/jquery.flot.resize.js');
        $this->document->addScript('view/javascript/jquery/flot/jquery.flot.tickrotor.js');

        $this->load->language('report/graph');

        #Get All Language Text
        $data = $this->language->all();

        $data['graph'] = $graph;

        $this->document->addScriptDeclarations($this->script($data));

        return $this->load->view('common/graph.tpl', $data);
    }

    public function graph()
    {
        $this->load->language('dashboard/charts');

        $this->load->model('dashboard/charts');

        if (isset($this->request->post['model'])) {
            $model = $this->request->post['model'];
        } else {
            $model = '';
        }

        if (!empty($this->request->post['function'])) {
            $function = $this->request->post['function'];
        } else {
            $function = '';
        }

        if (!empty($this->request->post['price'])) {
            $price = $this->request->post['price'];
        } else {
            $price = false;
        }

        if (!empty($this->request->post['title'])) {
            $title = $this->request->post['title'];
        } else {
            $title = '';
        }

        $json = $this->getChartData($model, 'get' . ucfirst($function), $price);

        $json['order']['label'] = $title;

        $this->response->setOutput(json_encode($json));
    }

    public function output()
    {
        //return $this->load->controller('report/sale_order');
    }

    public function export()
    {
        $route = $this->request->post['route'];
        $model = $this->request->post['model'];

        $modelFunction = 'get' . ucfirst($this->request->post['function']);

        $gets = explode('&amp;', $this->request->post['get']);

        unset($gets[0]);

        $get = array();

        foreach ($gets as $value) {
            $result = explode('=', $value);

            $get[$result[0]] = $result[1];
        }

        $this->load->language($route);

        $this->load->model('report/graph');
        $this->load->model('report/' . $model);

        $modelName = 'model_report_' . $model;

        $results = $this->{$modelName}->{$modelFunction}($get);

        if (!empty($results)) {
            $this->model_report_graph->download($results, $this->language->get('heading_title'));
        }
    }

    protected function getChartData($model, $modelFunction, $currency_format = false)
    {
        $this->load->model('report/' . $model);

        $modelName = 'model_report_' . $model;

        $json = array();

        $get = $this->request->get;

        if (!empty($this->request->get['filter_date_start'])) {
            $filter_date_start = $this->request->get['filter_date_start'];
        } else {
            $filter_date_start = date('Y-m-d', strtotime(date('Y') . '-' . date('m') . '-01'));
        }

        if (!empty($this->request->get['filter_date_end'])) {
            $filter_date_end = $this->request->get['filter_date_end'];
        } else {
            $filter_date_end = date('Y-m-d');
        }

        if (!empty($this->request->get['filter_group'])) {
            $filter_group = $this->request->get['filter_group'];
            $range = $filter_group;
        } elseif (!empty($this->request->get['range'])) {
            $filter_group = $range = $this->request->get['range'];
        }

        if (!empty($this->request->post['total'])) {
            $total_key = $this->request->post['total'];
        } else {
            $total_key = 'total';
        }

        $date_start = date_create($filter_date_start)->format('Y-m-d H:i:s');
        $date_end   = date_create($filter_date_end)->format('Y-m-d H:i:s');

        $diff_str = strtotime($filter_date_end) - strtotime($filter_date_start);
        $diff     = floor($diff_str / 3600 / 24) + 1;

        if (!isset($filter_group)) {
            $range = $this->getRange($diff);
        }

        switch ($range) {
            case 'day':
                $results    = $this->{$modelName}->{$modelFunction}($get);
                $str_date   = substr($date_start, 0, 10);
                $order_data = array();

                for ($i = 0; $i < $diff; $i++) {
                    $date = date_create($str_date)->modify('+' . $i . ' day')->format('Y-m-d');

                    $order_data[$date] = array(
                        'day'   => $date,
                        'total' => 0
                    );

                    $json['xaxis'][] = array($i, $date);
                }

                foreach ($results as $result) {
                    $total = $result[$total_key];

                    if ($currency_format) {
                        $total = $this->currency->format($result[$total_key], $this->config->get('config_currency'), '', false);
                    }

                    $str_date = substr($result['date_start'], 0, 10);
                    $date = date_create($str_date)->format('Y-m-d');

                    $order_data[$date] = array(
                        'day'   => $date,
                        'total' => $total
                    );
                }

                $i = $result_total = 0;

                foreach ($order_data as $key => $value) {
                    $json['order']['data'][] = array($i++, $value['total']);

                    $result_total += $value['total'];
                }

                break;
            case 'week':
                $results    = $this->{$modelName}->{$modelFunction}($get);
                $str_date   = substr($date_start, 0, 10);
                $order_data = array();

                for ($i = 0; $i < $diff; $i++) {
                    $date = date_create($str_date)->modify('+' . $i . ' day')->format('Y-m-d');

                    $order_data[$date] = array(
                        'week'  => $date,
                        'total' => 0
                    );

                    $json['xaxis'][] = array($i, $date);
                }

                foreach ($results as $result) {
                    $total = $result[$total_key];

                    if ($currency_format) {
                        $total = $this->currency->format($total, $this->config->get('config_currency'), '', false);
                    }

                    $str_date = substr($result['date_start'], 0, 10);
                    $date = date_create($str_date)->format('Y-m-d');

                    $order_data[$date] = array(
                        'week'  => $date,
                        'total' => $total
                    );
                }

                $i = $result_total = 0;

                foreach ($order_data as $key => $value) {
                    $json['order']['data'][] = array($i++, $value['total']);

                    $result_total += $value['total'];
                }

                break;
            case 'month':
                $results    = $this->{$modelName}->{$modelFunction}($get);
                $months     = $this->getMonths($date_start, $date_end);
                $order_data = array();

                for ($i = 0; $i < count($months); $i++) {
                    $order_data[$months[$i]] = array(
                        'month' => $months[$i],
                        'total' => 0
                    );

                    $json['xaxis'][] = array($i, $months[$i]);
                }

                foreach ($results as $result) {
                    $total = $result[$total_key];

                    if ($currency_format) {
                        $total = $this->currency->format($result[$total_key], $this->config->get('config_currency'), '', false);
                    }

                    $str_date = substr($result['date_start'], 0, 10);
                    $date = date_create($str_date)->format('Y-m-d');

                    $order_data[$date] = array(
                        'month' => $date,
                        'total' => $total
                    );
                }

                $i = $result_total = 0;

                foreach ($order_data as $key => $value) {
                    $json['order']['data'][] = array($i++, $value['total']);

                    $result_total += $value['total'];
                }

                break;
            case 'year':
                $results    = $this->{$modelName}->{$modelFunction}($get);
                $str_date   = substr($date_start, 0, 10);
                $order_data = array();
                $diff       = floor($diff / 365) + 1;

                for ($i = 0; $i < $diff; $i++) {
                    $date = date_create($str_date)->modify('+' . $i . ' year')->format('Y');

                    $order_data[$date] = array(
                        'year'  => $date,
                        'total' => 0
                    );

                    $json['xaxis'][] = array($i, $date);
                }

                foreach ($results as $result) {
                    $total = $result[$total_key];

                    if ($currency_format) {
                        $total = $this->currency->format($result[$total_key], $this->config->get('config_currency'), '', false);
                    }

                    $str_date = substr($result['date_start'], 0, 10);
                    $date = date_create($str_date)->format('Y');

                    $order_data[$date] = array(
                        'year'  => $date,
                        'total' => $total
                    );
                }

                $i = $result_total = 0;

                foreach ($order_data as $key => $value) {
                    $json['order']['data'][] = array($i++, $value['total']);

                    $result_total += $value['total'];
                }

                break;
            case 'special':
                $results = $this->{$modelName}->{$modelFunction}($get);
                $order_data = array();
                $diff = count($results);

                if (!empty($this->request->get['title'])) {
                    $name = $this->request->get['title'];
                } else {
                    $name = 'name';
                }

                for ($i = 0; $i < $diff; $i++) {
                    $order_data[$results[$i][$name]] = array(
                        'name'  => $results[$i][$name],
                        'total' => 0
                    );

                    $json['xaxis'][] = array($i, $results[$i][$name]);
                }

                foreach ($results as $result) {
                    $total = $result[$total_key];

                    if ($currency_format) {
                        $total = $this->currency->format($total, $this->config->get('config_currency'), '', false);
                    }

                    $order_data[$result[$name]] = array(
                        'name'  => $result[$name],
                        'total' => $total
                    );
                }

                $i = $result_total = 0;

                foreach ($order_data as $key => $value) {
                    $json['order']['data'][] = array($i++, $value['total']);

                    $result_total += $value['total'];
                }

                break;
        }

        $total = $result_total;

        if ($currency_format) {
            $total = $this->currency->format($total, $this->config->get('config_currency'));
        }

        $json['order']['total'] = $total;

        return $json;
    }

    protected function getMonths($date1, $date2)
    {
        $time1 = strtotime($date1);
        $time2 = strtotime($date2);

        $my = date('n-Y', $time2);

        $mesi = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');

        $months = array();

        $f = '';

        while ($time1 < $time2) {
            if (date('n-Y', $time1) != $f) {
                $f = date('n-Y', $time1);

                if (date('n-Y', $time1) != $my && ($time1 < $time2)) {
                    $str_mese = $mesi[(date('n', $time1) - 1)];

                    $months[] = $str_mese . " " . date('Y', $time1);
                }
            }

            $time1 = strtotime((date('Y-n-d', $time1) . ' +15days'));
        }

        $str_mese = $mesi[(date('n', $time2) - 1)];

        $months[] = $str_mese . " " . date('Y', $time2);

        return $months;
    }

    protected function getRange($diff)
    {
        if (isset($this->request->get['filter_group']) and !empty($this->request->get['filter_group']) and $this->request->get['filter_group'] != 'undefined') {
            $range = $this->request->get['filter_group'];
        } else {
            $range = 'day';
        }

        if ($diff < 365 and $range == 'year') {
            $range = 'month';
        }

        if ($diff < 28) {
            $range = 'week';
        }

        if ($diff < 7) {
            $range = 'day';
        }

        return $range;
    }

    protected function script($data)
    {
        if (isset($this->request->get['filter_date_start'])) {
            $filter_date_start = $this->request->get['filter_date_start'];
        } else {
            $filter_date_start = date('Y-m-d', strtotime(date('Y') . '-' . date('m') . '-01'));
        }

        if (isset($this->request->get['filter_date_end'])) {
            $filter_date_end = $this->request->get['filter_date_end'];
        } else {
            $filter_date_end = date('Y-m-d');
        }

        if (isset($this->request->get['filter_group'])) {
            $filter_range = $this->request->get['filter_group'];
        } else {
            $filter_range = 'week';
        }

        $this->load->model('localisation/currency');

        $currency = $this->model_localisation_currency->getCurrencyByCode($this->config->get('config_currency'));

        $symbol_left  = $currency['symbol_left'];
        $symbol_right = $currency['symbol_right'];

        $script = '' . chr(13);
        $script .= 'var start_date = \'' . $filter_date_start . '\';' . chr(13);
        $script .= 'var end_date = \'' . $filter_date_end . '\';' . chr(13);
        $script .= 'var block_range = \'' . $filter_range . '\';' . chr(13);

        $script .= '' . chr(13);

        $script .= '$(document).ready(function() {' . chr(13);
        $script .= '    var cb = function(start, end, label) {' . chr(13);
        $script .= '        start_date = start.format(\'YYYY-MM-DD\');' . chr(13);
        $script .= '        end_date   = end.format(\'YYYY-MM-DD\');' . chr(13);
        $script .= '' . chr(13);
        $script .= '        getCharts();' . chr(13);
        $script .= '    };' . chr(13);
        $script .= '' . chr(13);
        $script .= '    var lang_code = moment.locale();' . chr(13);
        $script .= '    var lang_data = moment.localeData();' . chr(13);
        $script .= '' . chr(13);
        $script .= '    getCharts();' . chr(13);
        $script .= '});' . chr(13);

        $script .= '' . chr(13);

        $script .= 'function getCharts() {' . chr(13);
        foreach ($data['graph'] as $key => $value) {
            $script .= '    ' . $key . '();' . chr(13);
        }
        $script .= '}' . chr(13);

        $script .= '' . chr(13);

        $script .= 'function getChart(tab, chart) {' . chr(13);
        $script .= '    $(\'#tab_toolbar dl\').removeClass(\'active\');' . chr(13);
        $script .= '    $(\'#tab_toolbar dl\').addClass(\'passive\');' . chr(13);
        $script .= '    $(\'#charts div\').removeClass(\'chart_active\');' . chr(13);
        $script .= '' . chr(13);
        $script .= '    $(\'#chart-\' + chart).addClass(\'chart_active\');' . chr(13);
        $script .= '    $(tab).removeClass(\'passive\');' . chr(13);
        $script .= '    $(tab).addClass(\'active\');' . chr(13);

        $script .= '' . chr(13);

        $script .= '    switch (chart) {' . chr(13);
        foreach ($data['graph'] as $key => $value) {
            $script .= '        case \'' . $key . '\' :' . chr(13);
            $script .= '            ' . $key . '();' . chr(13);
            $script .= '            break;' . chr(13);
        }
        $script .= '        default :' . chr(13);
        $script .= '            break;' . chr(13);
        $script .= '    }' . chr(13);
        $script .= '}' . chr(13);

        $script .= '' . chr(13);

        foreach ($data['graph'] as $key => $value) {
            $script .= 'function ' . $key . '() {' . chr(13);
            $script .= '    $(\'#' . $key . '_score\').html(\'<i class="fa fa-spinner fa-spin checkout-spin fa-2x"></i>\');' . chr(13);
            $script .= '    $(\'#chart-' . $key . '\').html(\'<div class="loading"><i class="fa fa-spinner fa-spin checkout-spin fa-5x"></i></div>\');' . chr(13);
            $script .= '' . chr(13);
            $script .= '    $.ajax({' . chr(13);
            $script .= '        type    : \'post\',' . chr(13);
            $script .= '        url     : \'' . $value['link'] . '\',' . chr(13);
            $script .= '        data    : \'model=' . $value['model'] . '&function=' . $value['function'] . '&price=' . $value['price'] . '&title=' . $value['title'] . '&total=' . $value['total'] .'\',' . chr(13);
            $script .= '        dataType: \'json\',' . chr(13);
            $script .= '        success : function(json) {' . chr(13);
            $script .= '            var option = {' . chr(13);
            $script .= '                shadowSize: 0,' . chr(13);
            $script .= '                lines     : {' . chr(13);
            $script .= '                    show: true' . chr(13);
            $script .= '                },' . chr(13);
            $script .= '                grid      : {' . chr(13);
            $script .= '                    backgroundColor: \'' . $value['background-color'] . '\',' . chr(13);
            $script .= '                    hoverable      : true' . chr(13);
            $script .= '                },' . chr(13);
            $script .= '                points    : {' . chr(13);
            $script .= '                    show     : true,' . chr(13);
            $script .= '                    fillColor: \'' . $value['color'] . '\'' . chr(13);
            $script .= '                },' . chr(13);
            $script .= '                xaxis     : {' . chr(13);
            $script .= '                    show       : true,' . chr(13);
            $script .= '                    ticks      : json[\'xaxis\'],' . chr(13);
            $script .= '                    rotateTicks: 45' . chr(13);
            $script .= '                },' . chr(13);
            $script .= '                yaxis     : {' . chr(13);

            if ($value['price']) {
                $script .= '                    mode        : "money",' . chr(13);
                $script .= '                    min         : 0,' . chr(13);
                $script .= '                    tickDecimals: 2,' . chr(13);
                $script .= '                    tickFormatter: function (v, axis) { return "' . $symbol_left . '" + v.toFixed(axis.tickDecimals) + "' . $symbol_right . '" }' . chr(13);
            } else {
                $script .= '                    min         : 0,' . chr(13);
                $script .= '                    tickDecimals: 0' . chr(13);
            }

            $script .= '                }' . chr(13);
            $script .= '            };' . chr(13);
            $script .= '' . chr(13);
            $script .= '            json[\'order\'][\'color\'] = "' . $value['color'] . '";' . chr(13);
            $script .= '' . chr(13);
            $script .= '            $.plot(\'#chart-' . $key . '\', [json[\'order\']], option);' . chr(13);
            $script .= '' . chr(13);
            $script .= '            $(\'#chart-' . $key . '\').bind(\'plothover\', function(event, pos, item) {' . chr(13);
            $script .= '                $(\'.tooltip\').remove();' . chr(13);
            $script .= '' . chr(13);
            $script .= '                if (item) {' . chr(13);
            $script .= '                    $(\'<div id="tooltip" class="tooltip top in"><div class="tooltip-arrow"></div><div class="tooltip-inner">\' + item.datapoint[1].toFixed(2) + \'</div></div>\').prependTo(\'body\');' . chr(13);
            $script .= '' . chr(13);
            $script .= '                    $(\'#tooltip\').css({' . chr(13);
            $script .= '                        position: \'absolute\',' . chr(13);
            $script .= '                        left    : item.pageX - ($(\'#tooltip\').outerWidth() / 2),' . chr(13);
            $script .= '                        top     : item.pageY - $(\'#tooltip\').outerHeight(),' . chr(13);
            $script .= '                        pointer : \'cusror\'' . chr(13);
            $script .= '                    }).fadeIn(\'slow\');' . chr(13);
            $script .= '' . chr(13);
            $script .= '                    $(\'#chart-' . $key . '\').css(\'cursor\', \'pointer\');' . chr(13);
            $script .= '                } else {' . chr(13);
            $script .= '                    $(\'#chart-' . $key . '\').css(\'cursor\', \'auto\');' . chr(13);
            $script .= '                }' . chr(13);
            $script .= '            });' . chr(13);
            $script .= '' . chr(13);
            $script .= '            $(\'#' . $key . '_score\').html(json[\'order\'][\'total\']);' . chr(13);
            $script .= '        },' . chr(13);
            $script .= '        error: function(xhr, ajaxOptions, thrownError) {}' . chr(13);
            $script .= '    });' . chr(13);
            $script .= '}' . chr(13);
            $script .= '' . chr(13);
        }

        $script .= '$(document).delegate(\'#button-export\', \'click\', function() {' . chr(13);
        $script .= '    route = getURLVar(\'route\');' . chr(13);
        $script .= '' . chr(13);
        $script .= '    html  = \'<form action="index.php?route=report/graph/export&token=\' +  getURLVar(\'token\') + \'" method="post" enctype="multipart/form-data" id="form-export" target="_blank" class="form-horizontal">\';' . chr(13);
        $script .= '    html += \'   <input type="hidden" name="route" value="\' + route + \'" />\';' . chr(13);
        $script .= '    html += \'   <input type="hidden" name="model" value="' . $value['model'] . '" />\';' . chr(13);
        $script .= '    html += \'   <input type="hidden" name="function" value="' . $value['function'] . '" />\';' . chr(13);
        $script .= '    html += \'   <input type="hidden" name="get" value="' . $value['link'] . '" />\';' . chr(13);
        $script .= '    html += \'</form>\';' . chr(13);
        $script .= '' . chr(13);
        $script .= '    $(\'body\').append(html);' . chr(13);
        $script .= '' . chr(13);
        $script .= '    $(\'#form-export\').submit();' . chr(13);
        $script .= '});' . chr(13);
        $script .= '' . chr(13);

        return $script;
    }
}