                                @foreach($filas as $fila)
                                    <tr class="matriz-row hover:bg-slate-50 transition" data-search="{{ strtolower($fila->nombres . ' ' . $fila->apellidos . ' ' . $fila->documento . ' ' . $fila->area) }}">
                                        <td class="px-4 py-3">
                                            <p class="font-bold text-slate-900">{{ $fila->nombres }} {{ $fila->apellidos }}</p>
                                            <p class="text-xs text-slate-500">C.C. {{ $fila->documento }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ $fila->area }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="text-[10px] font-bold uppercase rounded-full px-2.5 py-1 bg-slate-100 text-slate-600">
                                                {{ $fila->sistema === 'RENDIMIENTO_LABORAL' ? 'RL' : ($fila->sistema === 'ACUERDO_GESTION' ? 'AG' : $fila->sistema) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center font-semibold {{ $fila->nota_semestre_1 ? ($fila->sem1_provisional ? 'text-amber-600' : 'text-[#00594E]') : 'text-slate-300' }}" @if($fila->sem1_provisional) title="Nota provisional: {{ $fila->sem1_tramos }} tramos calificados" @endif>
                                            {{ $fila->nota_semestre_1 ?? '-' }}
                                            @if($fila->sem1_provisional)<span class="block text-[9px] font-bold uppercase text-amber-600">Parcial {{ $fila->sem1_tramos }}</span>@endif
                                        </td>
                                        <td class="px-4 py-3 text-center font-semibold {{ $fila->nota_semestre_2 ? ($fila->sem2_provisional ? 'text-amber-600' : 'text-[#00594E]') : 'text-slate-300' }}" @if($fila->sem2_provisional) title="Nota provisional: {{ $fila->sem2_tramos }} tramos calificados" @endif>
                                            {{ $fila->nota_semestre_2 ?? '-' }}
                                            @if($fila->sem2_provisional)<span class="block text-[9px] font-bold uppercase text-amber-600">Parcial {{ $fila->sem2_tramos }}</span>@endif
                                        </td>
                                        <td class="px-4 py-3 text-center font-bold {{ $fila->nota_anual ? 'text-[#00594E] bg-[#EAF2EF] rounded-lg' : 'text-slate-300' }}">
                                            {{ $fila->nota_anual ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-center"
                                            data-csv-sem1="{{ $fila->doc_semestre_1 ?? '-' }}"
                                            data-csv-sem2="{{ $fila->doc_semestre_2 ?? '-' }}"
                                            data-csv-anual="{{ $fila->doc_anual ?? '-' }}">
                                            <div class="inline-flex items-center gap-1.5 flex-wrap justify-center">
                                                @if($fila->doc_semestre_1)
                                                    <a href="{{ $fila->doc_semestre_1 }}" target="_blank" rel="noopener noreferrer" 
                                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[10px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition shadow-sm"
                                                       title="Ver documento firmado y radicado en Talento Humano (Semestre 1)">
                                                        <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                                                        Sem. 1
                                                    </a>
                                                @else
                                                    <span class="text-[10px] text-slate-300 font-semibold px-2 py-1 rounded-xl bg-slate-50 border border-slate-100" title="Sin documento radicado en Semestre 1">
                                                        Sem. 1: -
                                                    </span>
                                                @endif

                                                @if($fila->doc_semestre_2)
                                                    <a href="{{ $fila->doc_semestre_2 }}" target="_blank" rel="noopener noreferrer" 
                                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[10px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition shadow-sm"
                                                       title="Ver documento firmado y radicado en Talento Humano (Semestre 2)">
                                                        <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                                                        Sem. 2
                                                    </a>
                                                @else
                                                    <span class="text-[10px] text-slate-300 font-semibold px-2 py-1 rounded-xl bg-slate-50 border border-slate-100" title="Sin documento radicado en Semestre 2">
                                                        Sem. 2: -
                                                    </span>
                                                @endif

                                                @if($fila->doc_anual)
                                                    <a href="{{ $fila->doc_anual }}" target="_blank" rel="noopener noreferrer" 
                                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[10px] font-bold bg-amber-50 text-amber-800 hover:bg-amber-600 hover:text-white transition border border-amber-200/60 shadow-sm"
                                                       title="Ver documento firmado y radicado en Talento Humano (Anual)">
                                                        <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                                                        Anual
                                                    </a>
                                                @else
                                                    <span class="text-[10px] text-slate-300 font-semibold px-2 py-1 rounded-xl bg-slate-50 border border-slate-100" title="Sin documento radicado en Anual">
                                                        Anual: -
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
