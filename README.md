# ajax-data-table-yii2-component
Yii2 A component that handles requests ajax client side (query dataTable), and returns the data received into the database

init:

'ajaxData' => [
    'class' => 'app\components\AjaxData',
],

use:

$query = User::find();

$params = [];
$params['orderBy'] = 'id DESC';
$params['limit'] = 25;

$additional = ['>=', 'age', 25];

$users = Yii::$app->ajaxData->find($query, $params, $additional)->all();
