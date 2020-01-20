<?php
/**
 * Created by PhpStorm.
 * User: Victor Nemenenok
 * Date: 18.12.2019
 * Time: 14:40
 */

namespace Training\Clients;

abstract class Clients implements ClientsInterface {

    // Массив ошибок
    public $errors = [];

    // Контейнер для хранения экземпляров классов наследников
    protected static $_instances = [];
    // Конструктор (закрыт для исключения прямого создания инстанса)
    protected function __construct() {}

    // Получение инстанса
    public final static function getInstance() {
        // Вызываемый класс
        $class = get_called_class();
        // Если экземпляр класса еще не создан
        if (!isset(self::$_instances[$class])) {
            self::$_instances[$class] = new $class();
        }
        // Возврат инстанса
        return self::$_instances[$class];
    }
}