# Yii 2. Компонент ML для взаимодействия с системой Polyaxon (Через API)
Документация Polyaxon https://docs.polyaxon.com/references/polyaxon-api/

* Инициализация: `$training = new Training();`
* Запуск обучения: `$training->start($name);`, $name - наименование нового эксперимента
* Остановка обучения: `$training->stop($id);`, $id - идентификатор эксперимента
* Получение данных контейнера: `$training->check($id);`, $id - идентификатор эксперимента
* Получение списка контейнеров: `$training->list();`
