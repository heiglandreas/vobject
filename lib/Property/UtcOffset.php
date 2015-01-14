<?php

namespace Sabre\VObject\Property;

/**
 * UtcOffset property
 *
 * This object encodes UTC-OFFSET values.
 *
 * @copyright Copyright (C) 2011-2015 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class UtcOffset extends Text {

    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     *
     * @var string|null
     */
    public $delimiter = null;

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     *
     * @return string
     */
    public function getValueType() {

        return "UTC-OFFSET";

    }

    /**
     * Sets the XML value, as it would appear in a xCard or xCal object.
     *
     * The value must always be an array.
     *
     * @param array $value
     * @return void
     */
    function setXmlValue(array $value) {

        $value = array_map(
            function($value) {
                return str_replace(':', '', $value);
            },
            $value
        );
        parent::setXmlValue($value);

    }
}
