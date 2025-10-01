<?php
/**
 * Helper functions cho User Roles động
 */

function get_user_roles() {
    static $roles = null;
    if ($roles === null) {
        $roles = [
            'admin' => [
                'label' => 'Quản trị viên',
                'permissions' => db_get_col(
                    "SELECT permission FROM role_permissions WHERE role = ?", 
                    ['admin']
                )
            ],
            'manager' => [
                'label' => 'Quản lý',
                'permissions' => db_get_col(
                    "SELECT permission FROM role_permissions WHERE role = ?",
                    ['manager']  
                )
            ],
            'telesale' => [
                'label' => 'Telesale',
                'permissions' => db_get_col(
                    "SELECT permission FROM role_permissions WHERE role = ?",
                    ['telesale']
                )
            ]
        ];
    }
    return $roles;
}

function user_has_permission($user_id, $permission) {
    $user = db_get_row("SELECT role FROM users WHERE id = ?", [$user_id]);
    if (!$user) return false;
    
    $count = db_get_var(
        "SELECT COUNT(*) FROM role_permissions WHERE role = ? AND permission = ?",
        [$user['role'], $permission]
    );
    
    return $count > 0;
}
?>