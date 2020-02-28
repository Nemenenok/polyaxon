<?php
/**
 * Created by PhpStorm.
 * User: Victor Nemenenok
 * Date: 20.12.2019
 * Time: 10:10
 */

namespace Training;

use models\Settings;

class Training {

    // Контейнер для хранения обьекта клиента
    public $client;

    /**
     * Конструктор.
     * Получение инстанса клиента.
     */
    public function __construct() {

        // Название класса системы обучения
        $ClientClassName = __NAMESPACE__ . "\\Clients\\" . Settings::getByName("training"); 

        // Получение экземпляра класса
        $this->client = $ClientClassName::getInstance();
    }

    /**
     * Получение списка контейнеров обучения
     *
     * @return array
     */
    public function list(): array {
        // Возврат результата
        return [
            'data' => $this->client->list(),
            'error_message' => $this->client->errors,
        ];
    }

    /**
     * Запуск обучения
     *
     * @param string $name
     * @return array
     */
    public function start(string $name): array {
        // Возврат результата
        return [
            'data' => $this->client->start($name),
            'error_message' => $this->client->errors,
        ];
    }

    /**
     * Остановка обучения
     *
     * @param int $id
     * @return array
     */
    public function stop(int $id): array {
        // Возврат результата
        return [
            'data' => $this->client->stop($id),
            'error_message' => $this->client->errors,
        ];
    }

    /**
     * Получение данных контейнера
     *
     * @param int $id
     * @return array
     */
    public function check(int $id): array {
        // Возврат результата
        return [
            'data' => $this->client->check($id),
            'error_message' => $this->client->errors,
        ];
    }
}
