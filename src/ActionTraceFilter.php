<?php


namespace yii\yltrace;


use OpenTelemetry\API\Globals;
use Yii;
use yii\base\ActionFilter;

class ActionTraceFilter extends ActionFilter
{
    private $_scope;
    private $_span;

    public function beforeAction($action)
    {
        // 获取 tracer
        if(!isset($action->actionMethod)){
            return parent::beforeAction($action);
        }
        list($init, $rootSpan) = YoulianSpan::getRootSpan($action->actionMethod);
        if(!$init){
            $scope = $rootSpan->activate();
            $tracer = YoulianSpan::getTracer();
            $childSpan = $tracer->spanBuilder($action->actionMethod)->startSpan();
            $this->_scope = $scope;
            $this->_span = $childSpan;
        }
        $meter = Globals::meterProvider()->getMeter('ylmeter');
        $meter->createCounter($action->actionMethod)->add(1);
        return parent::beforeAction($action);
    }

    public function afterAction($action, $result)
    {
        if(isset($this->_span)){
            $this->_span->end();
            $this->_scope->detach();
        }
        return parent::afterAction($action, $result);
    }
}