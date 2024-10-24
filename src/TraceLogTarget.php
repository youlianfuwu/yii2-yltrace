<?php


namespace yii\yltrace;


use OpenTelemetry\API\Globals;
use Yii;
use yii\debug\LogTarget;

class TraceLogTarget extends LogTarget
{
    /**
     * @param \yii\debug\Module $module
     * @param array $config
     */
    public function __construct($module, $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Exports log messages to a specific destination.
     * Child classes must implement this method.
     * @throws \yii\base\Exception
     */
    public function export()
    {
        $profileMessage = array_filter($this->messages, function ($item){
            return $item[1] > 8;
        });
        $rootSpan = YoulianSpan::getRootSpan("")[1];
        if(!$rootSpan->getContext()->isSampled()){
            return;
        }
        if(empty($profileMessage)){
            $rootSpan->end();
            return;
        }
        $timings = Yii::getLogger()->calculateTimings($profileMessage);
        $tracer = Globals::tracerProvider()->getTracer('yltrace');
        foreach ($timings as $timing){
            $scope = $rootSpan->activate();
            try {
                $startTs = intval($timing['timestamp'] * pow(10, 9));
                $endts = $startTs + $timing['duration']*pow(10, 9);
                $childSpan = $tracer->spanBuilder($timing['category'])->setStartTimestamp($startTs)->startSpan();
                $childSpan->setAttribute('info', $timing['info']);
                $childSpan->setAttribute('memory', $timing['memory']/(8*1024));
                $childSpan->setAttribute('net.host.ip', Yii::$app->tracer->hostName);
                $childSpan->end($endts);
            } catch (\Exception $e) {
            } finally {
                $scope->detach();
            }
        }

        $summary = $this->collectSummary();
        $data = [];
        $data['summary'] = $summary;
        foreach ($summary as $key => $value) {
            $rootSpan->setAttribute($key, $value);
        }
        // 销毁 Span
        $rootSpan->end();
    }


}