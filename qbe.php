<?php
/**
 * @class QbeComponent
 * Convert posted data entered in a pseudo Query by Example fashion
 * from a CakePHP Form into Model::find() acceptable conditions.
 *
 * @author: Thanassis Bakalidis
 * @version: 1.4,1
 */
class QbeComponent extends Object {
    // sesion keys for saving and retrieving controller data
    const CONDITIONS_SESSION_KEY = 'SRCH_COND';
    const FORM_DATA_SESSION_KEY = 'SRCH_DATA';

    // supported SQL operators
    private $SQL_OPERATORS = array(
        'IN', '<>', '>=', '<=',
        '>', '<', '~'
    );

    private $sessionDataKey;        // session key of last values of controller data
    private $sessionConditionsKey;  // session key of last prepared search conditions
    private $modelName;             // name of model to create search conditions for

    var $owner;     // the controller using the component

    /**
     * @name initialize
     * The initialize method is called before the controller's
     * beforeFilter method.
     */
    function initialize(&$controller, $settings=array())
    {
        $this->owner =& $controller;
        $this->modelName = $settings['ModelName'];

        // create speciffic keys for the model andcontroller
        $this->sessionConditionsKey = sprintf("%s-%s-%s",
                                self::CONDITIONS_SESSION_KEY,
                                $this->owner->name,
                                $this->modelName
                            );
        $this->sessionDataKey = sprintf("%s-%s-%s",
                                self::FORM_DATA_SESSION_KEY,
                                $this->owner->name,
                                $this->modelName
                            );
    }

    /**
     * @name getSearchConditions()
     * Return an array to be used as search conditions in a find
     * based on the controller's current data
     * @param restoreOwnerData  if set the controller's data will be restored
     * to values they had after the search page was last submitted
     */
    public function getSearchConditions($restoreOwnerData = false)
    {
        if (empty($this->owner->data)) {
            // attempt to read conditions from sesion
            $conditions = $this->getLastSearchConditions();
            // and if desired restore the controller's data
            if ($restoreOwnerData == true)
                $this->owner->data = $this->getLastSearchData();
        } else {
            // we have posted data. Atempt to rebuild conditons array
            $conditions = array();
            foreach( $this->owner->data[$this->modelName] as $key => $value) {
                if (empty($value))
                    continue;

                $operator = $this->extractOperator($value);

                if (is_array($value)) {
                    // this can only be a date field

                    // scan for posibly empty slots in the array and
                    // convert them into &'s, so we may turn the search into a LIKE
                    foreach ($value as $valueKey => $valueValue)
                        if (empty($valueValue))
                            $value[$valueKey] = '%';

                    $month = $value['month'];
                    $day = $value['day'];
                    $year = $value['year'];

                    // We want all three variables to be numeric so we 'll check their
                    // concatenation. After all PHP numbers as just strings with digits
                    if (!is_numeric($month.$day.$year) || !checkdate( $month, $day, $year))
                        $operator = 'LIKE';
                    $conditionsValue = "$year-$month-$day";
                } else {
                    // we have normal input,
                    // check the operator given
                    if ($operator === '' && !is_numeric($value)) {
                        // turn '='' to 'LIKE' for non numeric data
                        // numeric data will be treated as if they
                        // have an wquals operator
                        $operator = 'LIKE';
                        $value = str_replace('*', '%',  $value);
                        $value = str_replace('?', '.',  $value);
                    } else if ($operator === 'IN') {
                        // we need to convert the input string to an aray
                        // of the designated values
                        $operator = '';
                        $value = array_filter(explode( ' ', $value));
                    } else if ($operator === '~') {
                        // implement the between operator
                        // values have to be passed like in the IN operator
                        $operator = 'BETWEEN ? AND ?';
                        $value = array_filter(explode( ' ', $value));
                    }

                    $conditionsValue = $value;
                }

                $conditionsKey = $this->modelName.".$key $operator";
                // add the new condition entry
                $conditions[trim($conditionsKey)] = $conditionsValue;
            }

            // if we have some criteria, add them in the sesion
            $this->owner->Session->write($this->sessionConditionsKey, $conditions);
            $this->owner->Session->write($this->sessionDataKey, $this->owner->data);
        }

        return $conditions;
    }

    /**
     * @name getLastSearchData
     * Returns a copy of the owning controllers data values just after the search
     * page was last submitted..
     */
    public function getLastSearchData()
    {
        return $this->owner->Session->check($this->sessionDataKey)
                ? $this->owner->Session->read($this->sessionDataKey)
                : array();
    }

    /**
     * @name getLastSearchConditions
     * Returns a copy of the owning controllers data values just after the search
     * page was last submitted..
     */
    public function getLastSearchConditions()
    {
        return $this->owner->Session->check($this->sessionConditionsKey)
                ? $this->owner->Session->read($this->sessionConditionsKey)
                : array();
    }

    /**
     * @name clearSearchCriteria()
     * Clears the last data and conditions from the user's session so the next time
     * that the search form appears
     */
    public function clearSearchCriteria()
    {
        $this->owner->Session->delete($this->sessionDataKey);
        $this->owner->Session->delete($this->sessionConditionsKey);
    }

    /*
     * Check if the imput string starts with any of the known operators
     * and if ti does extract the operator and trim it accordingly
     */
    private function extractOperator(&$input)
    {
        if (is_array($input))
            return '';

        $input = trim($input);
        foreach ($this->SQL_OPERATORS as $operator) {
            if ($this->startsWith($input, $operator)) {
                $opLength = strlen($operator);
                $inputLength = strlen($input);
                $input = trim(substr( $input, $opLength, $inputLength - $opLength));
                return $operator;
            }
        }
        return '';
    }

    /*
     * return true of haystack starts wioth needle
     * Thanks to http://stackoverflow.com/questions/834303/php-startswith-and-endswith-functions
     */
    private function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

}
