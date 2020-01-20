<?php
/**
 * Created by PhpStorm.
 * User: Victor Nemenenok
 * Date: 18.12.2019
 * Time: 15:13
 */

namespace Training\Clients;

/**
 * Интерфейс описывает обязательные методы клиента
 */
interface ClientsInterface {

    // Список контейнеров
    public function list(): array;

    // Запуск обучения
    public function start(string $name): bool;

    // Остановка обучения
    public function stop(int $id): array;

    // Данные контейнера
    public function check(int $id): array;
}