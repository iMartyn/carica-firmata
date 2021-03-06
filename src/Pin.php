<?php

namespace Carica\Firmata {

  use Carica\Io;

  /**
   * Represents a single pin on the board.
   *
   * @property-read Board $board
   * @property-read int $pin pin index
   * @property-read array $supports array of pin modes and maimum values
   * @property-read int $maximum Maximum value of the current mode
   * @property int $mode Get/set the pin mode
   * @property int $value Get/set the pin value using an analog integer value
   * @property float $analog Get/set the pin value using a float between 0 and 1
   * @property bool $digital Get/set the pin value using an boolean value
   */
  class Pin
    implements Io\Event\HasEmitter {

    use Io\Event\Emitter\Aggregation;

    /*
     * io mode constants for easier access, map to board pin modes
     */
    const MODE_UNKNOWN = Board::PIN_MODE_UNKNOWN;
    const MODE_INPUT = Board::PIN_MODE_INPUT;
    const MODE_OUTPUT = Board::PIN_MODE_OUTPUT;
    const MODE_ANALOG = Board::PIN_MODE_ANALOG;
    const MODE_PWM = Board::PIN_MODE_PWM;
    const MODE_SERVO = Board::PIN_MODE_SERVO;
    const MODE_SHIFT = Board::PIN_MODE_SHIFT;
    const MODE_I2C = Board::PIN_MODE_I2C;

    /**
     * @var Board
     */
    private $_board = NULL;
    /**
     * @var integer
     */
    private $_pin = 0;
    /**
     * Array of supported modes and resolutions
     *
     * @var array(integer => integer)
     */
    private $_supports = array();
    /**
     * @var integer
     */
    private $_mode = self::MODE_UNKNOWN;
    /**
     * @var integer
     */
    private $_value = 0;

    /**
     * Was the mode sent at least once to sync it with the board.
     * @var boolean
     */
    private $_modeInitialized = FALSE;
    /**
     * Was the value sent at least once to sync it with the board.
     * @var boolean
     */
    private $_valueInitialized = FALSE;

    /**
     * Create a pin object for the specified board and pin id. Provide informations
     * about the supported modes.
     *
     * @param Board $board
     * @param integer $pin
     * @param array $supports
     */
    public function __construct(Board $board, $pin, array $supports) {
      $this->_board = $board;
      $this->_pin = (int)$pin;
      $this->_supports = $supports;
      $modes = array_keys($supports);
      $this->_mode = isset($modes[0]) ? $modes[0] : self::MODE_UNKNOWN;
      $this->attachEvents();
    }

    private function attachEvents() {
      $that = $this;
      if ($events = $this->board->events()) {
        $events->on(
          'pin-state-'.$this->_pin,
          function ($mode, $value) use ($that) {
            $that->onUpdatePinState($mode, $value);
          }
        );
        $events->on(
          'analog-read-'.$this->_pin,
          function ($value) use ($that) {
            $that->onUpdateValue($value);
          }
        );
        $events->on(
          'digital-read-'.$this->_pin,
          function ($value) use ($that) {
            $that->onUpdateValue($value);
          }
        );
      }
    }

    /**
     * Callback function for pin state updates from the board.
     *
     * @param integer $mode
     * @param integer $value
     */
    private function onUpdatePinState($mode, $value) {
      $this->_modeInitialized = TRUE;
      $this->_valueInitialized = TRUE;
      if ($this->_mode != $mode || $this->_value != $value) {
        if ($this->_mode != $mode) {
          $this->_mode = $mode;
          $this->emitEvent('change-mode', $this);
        }
        if ($this->_value != $value) {
          $this->_value = $value;
          $this->emitEvent('change-value', $this);
        }
        $this->emitEvent('change', $this);
      }
    }

    /**
     * Callback function for pin value changes sent from the board.
     *
     * @param integer $value
     */
    private function onUpdateValue($value) {
      $this->_valueInitialized = TRUE;
      if ($this->_value != $value) {
        $this->_value = $value;
        $this->emitEvent('change-value', $this);
        $this->emitEvent('change', $this);
      }
    }

    /**
     * Define usable properties
     *
     * @param string $name
     * @return boolean
     */
    public function __isset($name) {
      switch ($name) {
      case 'board' :
      case 'pin' :
      case 'supports' :
      case 'mode' :
      case 'value' :
        return isset($this->{'_'.$name});
      case 'digital' :
      case 'analog' :
        return isset($this->_value);
      }
      return FALSE;
    }

    /**
     * Getter mapping for the object properties
     *
     * @param string $name
     * @throws \LogicException
     * @return mixed
     */
    public function __get($name) {
      switch ($name) {
      case 'board' :
        return $this->_board;
      case 'pin' :
        return $this->_pin;
      case 'supports' :
        return $this->_supports;
      case 'mode' :
        return $this->_mode;
      case 'value' :
        return $this->_value;
      case 'maximum' :
        return $this->getMaximum();
      case 'digital' :
        return ($this->_value == Board::DIGITAL_HIGH);
      case 'analog' :
        return $this->getAnalog();
      }
      throw new \LogicException(sprintf('Unknown property %s::$%s', get_class($this), $name));
    }

    /**
     * Setter for the writeable properties
     *
     * @param string $name
     * @param mixed $value
     * @throws \LogicException
     */
    public function __set($name, $value) {
      switch ($name) {
      case 'mode' :
        $this->setMode($value);
        return;
      case 'value' :
        $this->setValue($value);
        return;
      case 'digital' :
        $this->setDigital($value);
        return;
      case 'analog' :
        $this->setAnalog($value);
        return;
      }
      throw new \LogicException(
        sprintf('Property %s::$%s can not be written', get_class($this), $name)
      );
    }

    /**
     * Setter method for the mode property.
     *
     * @param integer $mode
     *
     * @throws Exception\UnsupportedMode
     */
    public function setMode($mode) {
      $mode = (int)$mode;
      if (!array_key_exists($mode, $this->_supports)) {
        throw new Exception\UnsupportedMode($this->_pin, $mode);
      }
      if ($this->_modeInitialized && $this->_mode == $mode) {
        return;
      }
      $this->_mode = $mode;
      $this->_modeInitialized = TRUE;
      $this->_board->pinMode($this->_pin, $mode);
      $this->emitEvent('change-mode', $this);
      $this->emitEvent('change', $this);
    }

    /**
     * Setter method for the digital property. Allows to change the value between low and high
     * using boolean values
     *
     * @param boolean $isActive
     */
    public function setDigital($isActive) {
      $value = (boolean)$isActive ? Board::DIGITAL_HIGH : Board::DIGITAL_LOW;
      if ($this->_valueInitialized && $this->_value == $value) {
        return;
      }
      $this->_value = $value;
      $this->_valueInitialized = TRUE;
      $this->_board->digitalWrite($this->_pin, $value);
      $this->emitEvent('change-value', $this);
      $this->emitEvent('change', $this);
    }

    /**
     * Getter method for the anlog value
     * @return float between 0 and 1
     */
    public function getAnalog() {
      return ($this->maximum > 0) ? $this->_value / $this->maximum : 0;
    }

    /**
     * Setter method for the analog property. Allows to set change the value on the pin.
     *
     * @param $percent
     *
     * @internal param float $value between 0 and 1
     */
    public function setAnalog($percent) {
      $resolution = $this->maximum;
      $value = round($percent * $resolution);
      if ($value < 0) {
        $value = 0;
      } elseif ($value > $resolution) {
        $value = $resolution;
      }
      $this->setValue($value);
    }

    /**
     * Setter method for the analog property. Allows to set change the value on the pin.
     * @param float $value between 0 and 1
     */
    public function setValue($value) {
      $value = (int)$value;
      if ($this->_valueInitialized && $this->_value == $value) {
        return;
      }
      $this->_value = $value;
      $this->_valueInitialized = TRUE;
      $this->_board->analogWrite($this->_pin, $value);
      $this->emitEvent('change-value', $this);
      $this->emitEvent('change', $this);
    }

    /**
     * Return the maximum value of the current mode
     *
     * @return integer
     */
    public function getMaximum() {
      return $this->_supports[$this->_mode];
    }

    /**
     * Does the pin support the given mode
     *
     * @param integer $mode
     * @return boolean
     */
    public function supports($mode) {
      return array_key_exists($mode, $this->_supports);
    }
  }
}
