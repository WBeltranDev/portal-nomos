<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plazo para recursos
    |--------------------------------------------------------------------------
    |
    | Días hábiles (lunes a viernes, sin contar festivos) que tiene el evaluado
    | para radicar recursos desde que se le notifica la nota. Un traslado de
    | evaluador no se confirma mientras el tramo de origen esté dentro de este
    | plazo o tenga un recurso pendiente de decisión.
    |
    */
    'dias_habiles_recursos' => (int) env('SERAG_DIAS_HABILES_RECURSOS', 5),

];
