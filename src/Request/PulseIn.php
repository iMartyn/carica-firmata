<?php

namespace Carica\Firmata\Request {

  use Carica\Firmata;

  class PulseIn extends Firmata\Request {

    private $_pin = 0;
    private $_value = Firmata\Board::DIGITAL_HIGH;
    private $_pulseLength = 0;
    private $_timeout = 1000000;

    public function __construct(
      Firmata\Board $board,
      $pin,
      $value = Firmata\Board::DIGITAL_HIGH,
      $pulseLength = 5,
      $timeout = 1000000
    ) {
      parent::__construct($board);
      $this->_pin = (int)$pin;
      $this->_value = (int)$value;
      $this->_pulseLength = (int)$pulseLength;
      $this->_timeout = (int)$timeout;
    }

    public function send() {
      $data = pack(
        'CCCC',
        Firmata\Board::START_SYSEX,
        Firmata\Board::PULSE_IN,
        $this->_pin,
        $this->_value
      );
      $data .= self::encodeBytes(
        pack(
         'NN',
         $this->_pulseLength,
         $this->_timeout
        )
      );
      $data .= pack(
        'C',
        Firmata\Board::END_SYSEX
      );
      $this->board()->stream()->write($data);
    }
  }
}