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
        Yii:error("beforeAction init : $init, key : ".$action->actionMethod." sampled : ".$rootSpan->getContext()->isSampled());
        if(!$init){
            $scope = $rootSpan->activate();
            $tracer = YoulianSpan::getTracer();
            $childSpan = $tracer->spanBuilder($action->actionMethod)->startSpan();
            $this->_scope = $scope;
            $this->_span = $childSpan;
        }
        return parent::beforeAction($action);
    }

    public function afterAction($action, $result)
    {
        Yii:error("afterAction isset span : ".isset($this->_span));
        if(isset($this->_span)){
            $this->_span->end();
            $this->_scope->detach();
        }
        return parent::afterAction($action, $result);
    }
}