<?php

namespace Simp\Pindrop\Permission;

use Simp\Pindrop\Database\DatabaseService;

class Permission
{
    public function __construct(protected DatabaseService $database_service) {}

    public function create(string $role, array $data)
    {
        $permission = [
            'role_key' => $role,
            'permission' => json_encode($data)
        ];

        $this->database_service->table('general_permissions')->where('role_key','=', $role)->delete();

        return $this->database_service->table('general_permissions')->insert($permission);
    }

    public function getPermissions()
    {
        $results = $this->database_service->table('general_permissions')->get();
        $permissions = [];

        foreach($results as $permission){
            $permissions[$permission['role_key']] = json_decode($permission['permission'] ?? "",true);
        }

        return $permissions;
    }

    public function getPermission(string $role_name)
    {
        $result = $this->database_service->table('general_permissions')->select(['permission'])->where('role_key','=',$role_name)->first();
        if (empty($result)) return [];
        return json_decode($result['permission'] ?? "", true) ?? [];
    }
}
