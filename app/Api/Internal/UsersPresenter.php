<?php

namespace App\Internal\Presenters;

use App\Admin\Presenters\BasePresenter;
use Nette\Application\AbortException;
use Nette\Application\Responses\JsonResponse;
use Nette\Database\Context;
use Nette\Http\IResponse;


/**
 * Class UsersPresenter
 * @package App\Api\Presenters
 */
class UsersPresenter extends BasePresenter
{
    private $database;

    /**
     * UsersPresenter constructor.
     * @param Context $database
     */
    public function __construct(Context $database) {
        $this->database = $database;
    }

    /**
     * @GET Get specific user with role from system Fiesta
     *
     * @param array $query
     * @throws AbortException
     */
    public function actionReadAll(array $query)
    {

        $name = isset($query['input']) ? $query['input'] : null;
        $type = isset($query['type']) ? $query['type'] : null;

        if (is_null($name) || is_null($type)) {
            return $this->sendSpecific400Error("name or input");
        }

        $this->sendJson(['items' => $this->prepareJsonToGetSpecificUsers($name, $type)]);
    }

    private function sendSpecific400Error($missing)
    {
        $response = $this->getHttpResponse();
        $response->setCode(IResponse::S400_BAD_REQUEST);
        $this->sendResponse(new JsonResponse(["status" => "error", "errors" => array(["message" => "this values cannot be null", "fields" => $missing])]));
    }

    private function prepareJsonToGetSpecificUsers($name, $type)
    {
        $users = $this->database->table("role_assignment");

        switch ($type) {
            case "members":
                $users->where("role", "member");
                break;
            case "internationals":
                $users->where("role", "international");
                break;
            case "users":
                $users->where("user.user_id.university", $this->userRepository->university);
                break;
        }

        $users
            ->where('data_user.name LIKE ? OR data_user.surname LIKE ? OR data_user LIKE ?', "%" . $name . "%", "%" . $name . "%", "%" . $name . "%")
            ->where("NOT (user.user_id.status?)", "uncompleted")
            ->select('data_user, data_user.name, data_user.surname')->group("data_user")->fetchAll();

        $results = array();
        foreach ($users as $user) {
            $avatarFilename = $this->userRepository->getProfileAvatar($user->ref("user","data_user")["signature"]);

            if (!$avatarFilename)
                $avatarFilename = "images/avatar/empty.jpg";

            $results[] = [
                'id' => $user["data_user"],
                'full_name' => $user["name"] . " " . $user["surname"],
                'avatar_url' => $avatarFilename,
                'email' => $user["data_user"],
            ];
        }

        return $results;
    }
}