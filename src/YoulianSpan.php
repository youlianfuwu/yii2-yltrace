<?php


namespace yii\yltrace;


use OpenTelemetry\API\Globals;
use Yii;

class YoulianSpan
{
    private static $_rootSpan;

    public static function getRootSpan($spanName){
        $init = false;
        if(!isset(static::$_rootSpan)){
            // 获取 tracer
            $tracer = self::getTracer();
            // 创建 Span
            $span = $tracer->spanBuilder($spanName)->startSpan();
            $span->setAttribute('net.host.ip', Yii::$app->tracer->hostName);
            static::$_rootSpan = $span;
            $init = true;
        }
        return array($init, static::$_rootSpan);
    }

    public static function getTracer(){
        return Globals::tracerProvider()->getTracer('yltrace');
    }

}