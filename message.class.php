<?php

namespace vk;

class message
{

    const MESSAGE_TYPE = 0;

    const TYPE_NEW_MESSAGE = 4;

    const MESSAGE_FLAG = 2;
    const MESSAGE_FROM = 3;
    const MESSAGE_TEXT = 6;


    const FLAG_OUTBOX = 2;

    private $data = null;

    /** @var vk */
    private $vk = null;

    public function __construct(vk $vk, $data)
    {
        $this->vk = $vk;
        $this->data = $data;
    }

    /**
     * @return vk
     */
    public function getVkObject() {
        return $this->vk;
    }

    public function isNewMassage()
    {
        return ($this->data[self::MESSAGE_TYPE] == self::TYPE_NEW_MESSAGE);
    }

    public function getFromUid()
    {
        return $this->data[self::MESSAGE_FROM];
    }

    public function isInBox()
    {
        return !($this->data[self::MESSAGE_FLAG] & self::FLAG_OUTBOX);
    }

    public function getFromUserName()
    {
        var_dump($this->getFromUid());
        $this->vk->getUserName($this->getFromUid());
    }

    public function getToUid()
    {

    }

    public function getText()
    {
        return (string)$this->data[self::MESSAGE_TEXT];
    }
}