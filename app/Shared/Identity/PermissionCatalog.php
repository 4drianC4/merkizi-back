<?php

namespace App\Shared\Identity;

class PermissionCatalog
{
    /** @return array<string, string> Module prefix => scope. */
    public static function modules(): array
    {
        return [
            'sucursales' => 'EMPRESA',
            'usuarios' => 'EMPRESA',
            'roles' => 'EMPRESA',
            'catalogo.categorias' => 'EMPRESA',
            'catalogo.productos' => 'EMPRESA',
            'caja.cajas' => 'SUCURSAL',
            'caja.sesiones' => 'SUCURSAL',
            'pos.venta' => 'SUCURSAL',
            'salon.mesas' => 'SUCURSAL',
        ];
    }

    /** @return array<string, array{module: string, action: string, scope: string}> */
    public static function definitions(): array
    {
        $definitions = [];
        foreach (self::modules() as $module => $scope) {
            $actions = match ($module) {
                'caja.sesiones' => ['ver', 'abrir', 'cerrar'],
                'pos.venta' => ['ver', 'crear', 'editar', 'cobrar', 'cerrar', 'confirmar_pago', 'historial', 'auditoria', 'exportar', 'devolver'],
                'salon.mesas' => ['ver', 'crear', 'editar', 'unir', 'transferir'],
                'usuarios' => ['ver', 'crear', 'editar', 'eliminar', 'permisos'],
                default => ['ver', 'crear', 'editar', 'eliminar'],
            };
            foreach ($actions as $action) {
                $definitions[$module.'.'.$action] = compact('module', 'action', 'scope');
            }
        }

        return $definitions;
    }

    /** @return list<string> */
    public static function employeePermissions(): array
    {
        return ['salon.mesas.ver', 'pos.venta.editar', 'pos.venta.cerrar', 'pos.venta.historial', 'sucursales.ver', 'catalogo.categorias.ver', 'catalogo.productos.ver', 'caja.cajas.ver', 'caja.sesiones.ver', 'caja.sesiones.abrir', 'caja.sesiones.cerrar', 'pos.venta.ver', 'pos.venta.crear', 'pos.venta.cobrar'];
    }
}
