<?php
/**
 * Generated by Protobuf protoc plugin.
 *
 * File descriptor : aruba-iot-sb-config.proto
 */


namespace aruba_telemetry;

/**
 * Protobuf message : aruba_telemetry.TransportConfig
 */
class TransportConfig extends \Protobuf\AbstractMessage
{

    /**
     * @var \Protobuf\UnknownFieldSet
     */
    protected $unknownFieldSet = null;

    /**
     * @var \Protobuf\Extension\ExtensionFieldMap
     */
    protected $extensions = null;

    /**
     * reportingPeriod optional uint32 = 1
     *
     * @var int
     */
    protected $reportingPeriod = null;

    /**
     * cellSize optional uint32 = 2
     *
     * @var int
     */
    protected $cellSize = null;

    /**
     * Check if 'reportingPeriod' has a value
     *
     * @return bool
     */
    public function hasReportingPeriod()
    {
        return $this->reportingPeriod !== null;
    }

    /**
     * Get 'reportingPeriod' value
     *
     * @return int
     */
    public function getReportingPeriod()
    {
        return $this->reportingPeriod;
    }

    /**
     * Set 'reportingPeriod' value
     *
     * @param int $value
     */
    public function setReportingPeriod($value = null)
    {
        $this->reportingPeriod = $value;
    }

    /**
     * Check if 'cellSize' has a value
     *
     * @return bool
     */
    public function hasCellSize()
    {
        return $this->cellSize !== null;
    }

    /**
     * Get 'cellSize' value
     *
     * @return int
     */
    public function getCellSize()
    {
        return $this->cellSize;
    }

    /**
     * Set 'cellSize' value
     *
     * @param int $value
     */
    public function setCellSize($value = null)
    {
        $this->cellSize = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function extensions()
    {
        if ( $this->extensions !== null) {
            return $this->extensions;
        }

        return $this->extensions = new \Protobuf\Extension\ExtensionFieldMap(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function unknownFieldSet()
    {
        return $this->unknownFieldSet;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromStream($stream, \Protobuf\Configuration $configuration = null)
    {
        return new self($stream, $configuration);
    }

    /**
     * {@inheritdoc}
     */
    public static function fromArray(array $values)
    {
        $message = new self();
        $values  = array_merge([
            'reportingPeriod' => null,
            'cellSize' => null
        ], $values);

        $message->setReportingPeriod($values['reportingPeriod']);
        $message->setCellSize($values['cellSize']);

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public static function descriptor()
    {
        return \google\protobuf\DescriptorProto::fromArray([
            'name'      => 'TransportConfig',
            'field'     => [
                \google\protobuf\FieldDescriptorProto::fromArray([
                    'number' => 1,
                    'name' => 'reportingPeriod',
                    'type' => \google\protobuf\FieldDescriptorProto\Type::TYPE_UINT32(),
                    'label' => \google\protobuf\FieldDescriptorProto\Label::LABEL_OPTIONAL()
                ]),
                \google\protobuf\FieldDescriptorProto::fromArray([
                    'number' => 2,
                    'name' => 'cellSize',
                    'type' => \google\protobuf\FieldDescriptorProto\Type::TYPE_UINT32(),
                    'label' => \google\protobuf\FieldDescriptorProto\Label::LABEL_OPTIONAL()
                ]),
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function toStream(\Protobuf\Configuration $configuration = null)
    {
        $config  = $configuration ?: \Protobuf\Configuration::getInstance();
        $context = $config->createWriteContext();
        $stream  = $context->getStream();

        $this->writeTo($context);
        $stream->seek(0);

        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function writeTo(\Protobuf\WriteContext $context)
    {
        $stream      = $context->getStream();
        $writer      = $context->getWriter();
        $sizeContext = $context->getComputeSizeContext();

        if ($this->reportingPeriod !== null) {
            $writer->writeVarint($stream, 8);
            $writer->writeVarint($stream, $this->reportingPeriod);
        }

        if ($this->cellSize !== null) {
            $writer->writeVarint($stream, 16);
            $writer->writeVarint($stream, $this->cellSize);
        }

        if ($this->extensions !== null) {
            $this->extensions->writeTo($context);
        }

        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function readFrom(\Protobuf\ReadContext $context)
    {
        $reader = $context->getReader();
        $length = $context->getLength();
        $stream = $context->getStream();

        $limit = ($length !== null)
            ? ($stream->tell() + $length)
            : null;

        while ($limit === null || $stream->tell() < $limit) {

            if ($stream->eof()) {
                break;
            }

            $key  = $reader->readVarint($stream);
            $wire = \Protobuf\WireFormat::getTagWireType($key);
            $tag  = \Protobuf\WireFormat::getTagFieldNumber($key);

            if ($stream->eof()) {
                break;
            }

            if ($tag === 1) {
                \Protobuf\WireFormat::assertWireType($wire, 13);

                $this->reportingPeriod = $reader->readVarint($stream);

                continue;
            }

            if ($tag === 2) {
                \Protobuf\WireFormat::assertWireType($wire, 13);

                $this->cellSize = $reader->readVarint($stream);

                continue;
            }

            $extensions = $context->getExtensionRegistry();
            $extension  = $extensions ? $extensions->findByNumber(__CLASS__, $tag) : null;

            if ($extension !== null) {
                $this->extensions()->add($extension, $extension->readFrom($context, $wire));

                continue;
            }

            if ($this->unknownFieldSet === null) {
                $this->unknownFieldSet = new \Protobuf\UnknownFieldSet();
            }

            $data    = $reader->readUnknown($stream, $wire);
            $unknown = new \Protobuf\Unknown($tag, $wire, $data);

            $this->unknownFieldSet->add($unknown);

        }
    }

    /**
     * {@inheritdoc}
     */
    public function serializedSize(\Protobuf\ComputeSizeContext $context)
    {
        $calculator = $context->getSizeCalculator();
        $size       = 0;

        if ($this->reportingPeriod !== null) {
            $size += 1;
            $size += $calculator->computeVarintSize($this->reportingPeriod);
        }

        if ($this->cellSize !== null) {
            $size += 1;
            $size += $calculator->computeVarintSize($this->cellSize);
        }

        if ($this->extensions !== null) {
            $size += $this->extensions->serializedSize($context);
        }

        return $size;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->reportingPeriod = null;
        $this->cellSize = null;
    }

    /**
     * {@inheritdoc}
     */
    public function merge(\Protobuf\Message $message)
    {
        if ( ! $message instanceof \aruba_telemetry\TransportConfig) {
            throw new \InvalidArgumentException(sprintf('Argument 1 passed to %s must be a %s, %s given', __METHOD__, __CLASS__, get_class($message)));
        }

        $this->reportingPeriod = ($message->reportingPeriod !== null) ? $message->reportingPeriod : $this->reportingPeriod;
        $this->cellSize = ($message->cellSize !== null) ? $message->cellSize : $this->cellSize;
    }


}
