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
            'catalogo.stock' => 'SUCURSAL',
            'caja.cajas' => 'SUCURSAL',
            'caja.sesiones' => 'SUCURSAL',
            'caja.movimientos' => 'SUCURSAL',
            'pos.venta' => 'SUCURSAL',
            'salon.mesas' => 'SUCURSAL',
        ];
    }

    /** @return array<string, array{module: string, action: string, scope: string, description?: string}> */
    public static function definitions(): array
    {
        $definitions = [];
        foreach (self::modules() as $module => $scope) {
            $actions = match ($module) {
                'catalogo.stock' => ['ver'],
                'caja.movimientos' => ['crear'],
                'caja.sesiones' => ['ver', 'abrir', 'cerrar', 'arquear'],
                'pos.venta' => ['ver', 'crear', 'editar', 'cobrar', 'cerrar', 'confirmar_pago', 'historial', 'auditoria', 'exportar', 'devolver', 'descontar', 'anular'],
                'salon.mesas' => ['ver', 'crear', 'editar', 'unir', 'transferir'],
                'usuarios' => ['ver', 'crear', 'editar', 'eliminar', 'permisos'],
                default => ['ver', 'crear', 'editar', 'eliminar'],
            };
            foreach ($actions as $action) {
                $definitions[$module.'.'.$action] = compact('module', 'action', 'scope');
            }
        }

        foreach ([
            'caja.sesiones.abrir' => 'Abrir y operar caja asignada.',
            'pos.venta.cobrar' => 'Cobrar ventas, incluidas ventas en efectivo.',
            'caja.movimientos.crear' => 'Registrar gastos menores y retiros.',
            'caja.sesiones.arquear' => 'Realizar arqueo de caja.',
            'caja.sesiones.cerrar' => 'Realizar cierre de caja.',
            'catalogo.productos.ver' => 'Consultar catálogo de productos.',
            'catalogo.stock.ver' => 'Consultar stock por sucursal.',
            'pos.venta.descontar' => 'Aplicar descuentos manuales.',
            'pos.venta.anular' => 'Anular o cancelar comprobantes.',
        ] as $code => $description) {
            $definitions[$code]['description'] = $description;
        }

        return $definitions;
    }

    /** @return list<string> */
    public static function employeePermissions(): array
    {
        return ['salon.mesas.ver', 'pos.venta.editar', 'pos.venta.cerrar', 'pos.venta.historial', 'sucursales.ver', 'catalogo.categorias.ver', 'catalogo.productos.ver', 'caja.cajas.ver', 'caja.sesiones.ver', 'caja.sesiones.abrir', 'caja.sesiones.cerrar', 'pos.venta.ver', 'pos.venta.crear', 'pos.venta.cobrar'];
    }
}
