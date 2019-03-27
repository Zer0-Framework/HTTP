# HTTP

## Контроллеры
Контроллеры лежат в `\*\Controllers\` (наследуются от `\Zer0\HTTP\AbstractController`).
Чтобы создать контроллер достаточно создать класс с одним или несколькими публичными методами `*Action`,
а также добавить пути указывающие на него в conf/Routes.

Перед выполнением любого `*Action` происходит вызов `before()`, после — `after()`, в них принято выносить общие операции,
такие как проверка авторизации пользователя.

Методы `*Action` могут возвращать экземпляр `\Zer0\HTTP\Responses\*`, в против случае если возвратное значение не null, то оно будет
выведено в формате JSON (`\Zer0\HTTP\Responses\JSON`). Это поведение может быть переопределено в методе `renderResponse()` контроллера.


## Конфигурирование роутов
Формат следующий:

````
getting_started:
    path:     /getting-started/{action}
    defaults: {controller: GettingStarted, action: intro}
    methods: [GET, POST]
    export: [JS]
...
````

 * path — путь данного роута, допускаются параметры (переменные) в фигурных скобках.
 * path_export — необязательный путь, который будет вместо _path_ при составлении URL. Удобно при использовании CDN.
 * defaults — необязательный набор значений по-умолчанию.
 * methods — необязательный список допустимых методов.
 * export — необязательный список того, куда экспортировать данный роут.
 * sort — необязательный параметр, по которому происходит сортировка, по-умолчанию 0.
 Чем меньше значение, тем раньше он будет выполнен.

Если action не задан, то присваивается значение index.
Controller обязан быть задан чтобы запросы могли выполняться.

Класс `\Zer0\HTTP` (`$this->http`) имеет метод

`public function buildUrl(string $routeName, $params = [], array $query = []): string`

позволяющий построить URL, например так: `$this->http->buildUrl('getting_started', 'routes', ['foo' => 'bar']);`
При конфигурации роута `getting_started` как в примере выше результатом будет `/getting-started/routes?foo=bar`

Также можно пользоваться аналогичной функцией в Javascript — `Routes.url()`. При этом доступны только те роуты,
у которых в списке export есть JS.

Жестко указывать URL в коде не рекомендуется.

*Внимание!* Учтите, что после внесение изменений в конфигурацию роутов необходимо
выполнить `cli http build-all` (`make build-backend`)

## ЧаВО

### Почему роутинг осуществляется в Nginx?

 Такой подход имеет ряд преимуществ:
1. Количество роутов не влияет на производительность, так как Nginx
  не идёт перебором по списку роутов, как роутеры запросов, написанные на PHP, а используя Btree+ индекс
 по заякоренной части за _O(log n)_ находит подходящие location'ы.
2. Разные роуты можно при желании направить на разные пулы PHP-FPM и не составляет труда добавить, например, limit_req.
3. PHP-части даже не нужно читать и держать в памяти конфигурацию роутов.
