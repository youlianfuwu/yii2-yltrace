<?php


namespace yii\yltrace;


use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;

class YoulianTracer extends Component implements BootstrapInterface
{

    public $serviceName;

    public $hostName;

    public $endpoint;

    public $logTarget;

    private $_config;


    public function __construct($config = [])
    {
        $this->_config = $config;
        $this->serviceName = $config['serviceName'];
        $this->hostName = $config['hostName'];
        $this->endpoint = $config['endpoint'];
        parent::__construct($config);
    }

    public function init()
    {
        $this->initOpenTelemetry();
        parent::init();
    }

    function initOpenTelemetry()
    {
        // 1. 设置 OpenTelemetry 资源信息
        $resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => $this->serviceName, # 应用名，必填
            ResourceAttributes::HOST_NAME => $this->hostName, # 应用名，必填
        ])));

        //2. 创建将 Span 输出到控制台的 SpanExporter，可选
//         $spanExporter = new SpanExporter(
//             (new StreamTransportFactory())->create('php://stdout', 'application/json')
//         );


        // 2. 创建通过 HTTP 上报 Span 的 SpanExporter
        $transport = (new OtlpHttpTransportFactory())->create($this->endpoint, 'application/x-protobuf');
        $spanExporter = new SpanExporter($transport);

        // 3. 创建全局的 TraceProvider，用于创建 tracer
        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(
                (new BatchSpanProcessorBuilder($spanExporter))->build()
            )
            ->setResource($resource)
            ->setSampler(new ParentBased(new AlwaysOnSampler()))
            ->build();

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)  // PHP 程序退出后自动关闭 tracerProvider，保证链路数据都被上报
            ->buildAndRegisterGlobal(); // 将 tracerProvider 添加到全局

    }


    /**
     * {@inheritdoc}
     */
    public function bootstrap($app)
    {
        $targets = Yii::$app->log->targets;
        foreach($targets as $target){
            if(get_class($target) == 'yii\yltrace\TraceLogTarget'){
                return;
            }
        }
        $this->logTarget['class'] = 'yii\yltrace\TraceLogTarget';
        $this->logTarget = Yii::createObject($this->logTarget, [$this]);
        $app->getLog()->targets['debug'] = $this->logTarget;
    }
}