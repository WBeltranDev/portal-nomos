<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Competencias de nivel jerárquico según "competencias nivel jerarquico - SERAG.xlsx"
 * (storage/app/competencias_catalogo.json es la fuente). Las que ya existen se
 * actualizan en el sitio para conservar su id; las nuevas se crean. Las que ya no
 * figuran se eliminan, salvo si hay evaluaciones que las usan: esas quedan
 * inactivas (historial) y dejan de ofrecerse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('competencia_catalogo', 'activo')) {
            Schema::table('competencia_catalogo', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('orden');
            });
        }

        $ruta = storage_path('app/competencias_catalogo.json');
        $catalogo = file_exists($ruta) ? (json_decode(file_get_contents($ruta), true) ?? []) : [];
        $orden = 100;

        foreach (['RENDIMIENTO_LABORAL', 'ACUERDO_GESTION'] as $sistema) {
            foreach ($catalogo[$sistema]['NIVEL_JERARQUICO'] ?? [] as $nivel => $items) {
                $existentes = DB::table('competencia_catalogo')
                    ->where(['sistema' => $sistema, 'tipo' => 'NIVEL_JERARQUICO', 'nivel_jerarquico' => $nivel])
                    ->get()->keyBy(fn ($c) => $this->clave($c->nombre));
                $vigentes = [];

                foreach ($items as $item) {
                    $datos = ['nombre' => $item['nombre'], 'afirmacion' => $item['afirmacion'] ?? null, 'orden' => $orden++, 'activo' => 1];
                    $previa = $existentes->get($this->clave($item['nombre']));
                    if ($previa) {
                        DB::table('competencia_catalogo')->where('id_competencia', $previa->id_competencia)->update($datos);
                        $vigentes[] = $previa->id_competencia;
                    } else {
                        $vigentes[] = DB::table('competencia_catalogo')->insertGetId($datos + ['sistema' => $sistema, 'tipo' => 'NIVEL_JERARQUICO', 'nivel_jerarquico' => $nivel]);
                    }
                }

                foreach ($existentes as $c) {
                    if (in_array($c->id_competencia, $vigentes, true)) {
                        continue;
                    }
                    if (DB::table('competencia_evaluada')->where('id_competencia', $c->id_competencia)->exists()) {
                        DB::table('competencia_catalogo')->where('id_competencia', $c->id_competencia)->update(['activo' => 0]);
                    } else {
                        DB::table('competencia_catalogo')->where('id_competencia', $c->id_competencia)->delete();
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // No se revierte: el catálogo anterior no se conserva.
    }

    private function clave(string $nombre): string
    {
        return Str::of(Str::ascii($nombre))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }
};
