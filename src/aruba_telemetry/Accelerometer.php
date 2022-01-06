<?php
/**
 * Generated by Protobuf protoc plugin.
 *
 * File descriptor : aruba-iot-nb-telemetry.proto
 */


namespace aruba_telemetry;

/**
 * Protobuf message : aruba_telemetry.Accelerometer
 */
class Accelerometer extends \Protobuf\AbstractMessage
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
     * x required float = 1
     *
     * @var float
     */
    protected $x = null;

    /**
     * y required float = 2
     *
     * @var float
     */
    protected $y = null;

    /**
     * z required float = 3
     *
     * @var float
     */
    protected $z = null;

    /**
     * status optional enum = 4
     *
     * @var \aruba_telemetry\AccelStatus
     */
    protected $status = null;

    /**
     * Check if 'x' has a value
     *
     * @return bool
     */
    public function hasX()
    {
        return $this->x !== null;
    }

    /**
     * Get 'x' value
     *
     * @return float
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * Set 'x' value
     *
     * @param float $value
     */
    public function setX($value)
    {
        $this->x = $value;
    }

    /**
     * Check if 'y' has a value
     *
     * @return bool
     */
    public function hasY()
    {
        return $this->y !== null;
    }

    /**
     * Get 'y' value
     *
     * @return float
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     * Set 'y' value
     *
     * @param float $value
     */
    public function setY($value)
    {
        $this->y = $value;
    }

    /**
     * Check if 'z' has a value
     *
     * @return bool
     */
    public function hasZ()
    {
        return $this->z !== null;
    }

    /**
     * Get 'z' value
     *
     * @return float
     */
    public function getZ()
    {
        return $this->z;
    }

    /**
     * Set 'z' value
     *
     * @param float $value
     */
    public function setZ($value)
    {
        $this->z = $value;
    }

    /**
     * Check if 'status' has a value
     *
     * @return bool
     */
    public function hasStatus()
    {
        return $this->status !== null;
    }

    /**
     * Get 'status' value
     *
     * @return \aruba_telemetry\AccelStatus
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set 'status' value
     *
     * @param \aruba_telemetry\AccelStatus $value
     */
    public function setStatus(\aruba_telemetry\AccelStatus $value = null)
    {
        $this->status = $value;
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
        if ( ! isset($values['x'])) {
            throw new \InvalidArgumentException('Field "x" (tag 1) is required but has no value.');
        }

        if ( ! isset($values['y'])) {
            throw new \InvalidArgumentException('Field "y" (tag 2) is required but has no value.');
        }

        if ( ! isset($values['z'])) {
            throw new \InvalidArgumentException('Field "z" (tag 3) is required but has no value.');
        }

        $message = new self();
        $values  = array_merge([
            'status' => null
        ], $values);

        $message->setX($values['x']);
        $message->setY($values['y']);
        $message->setZ($values['z']);
        $message->setStatus($values['status']);

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public static function descriptor()
    {
        return \google\protobuf\DescriptorProto::fromArray([
            'name'      => 'Accelerometer',
            'field'     => [
                \google\protobuf\FieldDescriptorProto::fromArray([
                    'number' => 1,
                    'name' => 'x',
                    'type' => \google\protobuf\FieldDescriptorProto\Type::TYPE_FLOAT(),
                    'label' => \google\protobuf\FieldDescriptorProto\Label::LABEL_REQUIRED()
                ]),
                \google\protobuf\FieldDescriptorProto::fromArray([
                    'number' => 2,
                    'name' => 'y',
                    'type' => \google\protobuf\FieldDescriptorProto\Type::TYPE_FLOAT(),
                    'label' => \google\protobuf\FieldDescriptorProto\Label::LABEL_REQUIRED()
                ]),
                \google\protobuf\FieldDescriptorProto::fromArray([
                    'number' => 3,
                    'name' => 'z',
                    'type' => \google\protobuf\FieldDescriptorProto\Type::TYPE_FLOAT(),
                    'label' => \google\protobuf\FieldDescriptorProto\Label::LABEL_REQUIRED()
                ]),
                \google\protobuf\FieldDescriptorProto::fromArray([
                    'number' => 4,
                    'name' => 'status',
                    'type' => \google\protobuf\FieldDescriptorProto\Type::TYPE_ENUM(),
                    'label' => \google\protobuf\FieldDescriptorProto\Label::LABEL_OPTIONAL(),
                    'type_name' => '.aruba_telemetry.AccelStatus'
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

        if ($this->x === null) {
            throw new \UnexpectedValueException('Field "\\aruba_telemetry\\Accelerometer#x" (tag 1) is required but has no value.');
        }

        if ($this->y === null) {
            throw new \UnexpectedValueException('Field "\\aruba_telemetry\\Accelerometer#y" (tag 2) is required but has no value.');
        }

        if ($this->z === null) {
            throw new \UnexpectedValueException('Field "\\aruba_telemetry\\Accelerometer#z" (tag 3) is required but has no value.');
        }

        if ($this->x !== null) {
            $writer->writeVarint($stream, 13);
            $writer->writeFloat($stream, $this->x);
        }

        if ($this->y !== null) {
            $writer->writeVarint($stream, 21);
            $writer->writeFloat($stream, $this->y);
        }

        if ($this->z !== null) {
            $writer->writeVarint($stream, 29);
            $writer->writeFloat($stream, $this->z);
        }

        if ($this->status !== null) {
            $writer->writeVarint($stream, 32);
            $writer->writeVarint($stream, $this->status->value());
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
                \Protobuf\WireFormat::assertWireType($wire, 2);

                $this->x = $reader->readFloat($stream);

                continue;
            }

            if ($tag === 2) {
                \Protobuf\WireFormat::assertWireType($wire, 2);

                $this->y = $reader->readFloat($stream);

                continue;
            }

            if ($tag === 3) {
                \Protobuf\WireFormat::assertWireType($wire, 2);

                $this->z = $reader->readFloat($stream);

                continue;
            }

            if ($tag === 4) {
                \Protobuf\WireFormat::assertWireType($wire, 14);

                $this->status = \aruba_telemetry\AccelStatus::valueOf($reader->readVarint($stream));

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

        if ($this->x !== null) {
            $size += 1;
            $size += 4;
        }

        if ($this->y !== null) {
            $size += 1;
            $size += 4;
        }

        if ($this->z !== null) {
            $size += 1;
            $size += 4;
        }

        if ($this->status !== null) {
            $size += 1;
            $size += $calculator->computeVarintSize($this->status->value());
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
        $this->x = null;
        $this->y = null;
        $this->z = null;
        $this->status = null;
    }

    /**
     * {@inheritdoc}
     */
    public function merge(\Protobuf\Message $message)
    {
        if ( ! $message instanceof \aruba_telemetry\Accelerometer) {
            throw new \InvalidArgumentException(sprintf('Argument 1 passed to %s must be a %s, %s given', __METHOD__, __CLASS__, get_class($message)));
        }

        $this->x = ($message->x !== null) ? $message->x : $this->x;
        $this->y = ($message->y !== null) ? $message->y : $this->y;
        $this->z = ($message->z !== null) ? $message->z : $this->z;
        $this->status = ($message->status !== null) ? $message->status : $this->status;
    }


}

