<div class="app-shell">
    <header class="site-header">
        <div class="site-header-inner">
            <a class="site-brand" href="#resumen" aria-label="Pulso Fiscal, ir al resumen">
                <span class="site-brand-mark" aria-hidden="true">▥</span>
                <span><strong>Pulso Fiscal</strong><small>Facturación de un vistazo</small></span>
            </a>

            <div class="site-header-actions">
                <span class="site-demo-label"><span class="site-demo-dot" aria-hidden="true"></span> Demo de portafolio</span>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="content">
            <div class="hero" id="resumen">
                <div>
                    <div class="eyebrow">VISIÓN GENERAL <span></span> {{ $today->translatedFormat('d F Y') }}</div>
                    <h1>Facturación, sin perder el hilo.</h1>
                    <p>De cada cifra a su factura y a sus pagos. Una vista clara de lo emitido, cobrado y pendiente.</p>
                </div>
                <div class="hero-badge"><span>●</span> Datos ficticios · MXN</div>
            </div>

            @if (! $organization)
                <div class="empty-setup">Aún no hay datos de demostración. Ejecuta las migraciones y el sembrador indicados en el README.</div>
            @else
                <section class="stats" aria-label="Indicadores al día de hoy">
                    <article class="stat-card"><div class="stat-top"><span>Emitido vigente</span><span class="stat-icon violet">↗</span></div><strong>${{ number_format($totals['issued'] / 100, 2) }}</strong><small>Facturas vigentes emitidas al corte</small><div class="stat-rule violet-bg"></div></article>
                    <article class="stat-card"><div class="stat-top"><span>Cobrado</span><span class="stat-icon green">✓</span></div><strong>${{ number_format($totals['collected'] / 100, 2) }}</strong><small>Pagos aplicados al corte</small><div class="stat-rule green-bg"></div></article>
                    <article class="stat-card"><div class="stat-top"><span>Saldo abierto</span><span class="stat-icon amber">◷</span></div><strong>${{ number_format($totals['open'] / 100, 2) }}</strong><small>Por cobrar de facturas vigentes</small><div class="stat-rule amber-bg"></div></article>
                    <article class="stat-card"><div class="stat-top"><span>Saldo vencido</span><span class="stat-icon rose">!</span></div><strong>${{ number_format($totals['overdue'] / 100, 2) }}</strong><small>Saldo abierto con fecha vencida</small><div class="stat-rule rose-bg"></div></article>
                </section>

                <div class="info-strip"><span>ⓘ</span><p>Estos indicadores no son una declaración fiscal: “emitido” y “cobrado” miden cosas diferentes. Todas las cantidades provienen de registros de muestra y se calculan en centavos enteros.</p></div>

                @if ($showImportModal)
                <div class="import-modal-backdrop" x-data x-trap.inert.noscroll="true" x-init="$nextTick(() => $refs.close.focus())" x-on:keydown.escape.window="$wire.closeImport()" wire:click.self="closeImport" wire:key="import-modal">
                <section class="import-modal" role="dialog" aria-modal="true" aria-labelledby="import-heading">
                    <header class="import-modal-bar">
                        <div><span>ESCENARIO TEMPORAL</span><h2 id="import-heading">Importar facturas ficticias</h2></div>
                        <button type="button" class="import-modal-close" x-ref="close" wire:click="closeImport" aria-label="Cerrar importación">×</button>
                    </header>
                    <div class="import-modal-content">
                    <div class="import-intro">
                        <p>Prueba hasta 50 filas por CSV (100 por sesión). Primero verás errores, duplicados y el impacto en los indicadores; solo las filas válidas se agregan cuando confirmes.</p>
                        <a class="import-sample" href="{{ asset('samples/facturas-demo.csv') }}" download>Descargar CSV de ejemplo ↓</a>
                    </div>
                    <div class="import-controls" x-data="{ async readFile(event) { const file = event.target.files[0]; if (!file) return; if (file.size > 100000) { $wire.importMessage = 'El archivo supera el límite de 100 KB.'; event.target.value = ''; return; } await $wire.set('csvText', await file.text()); await $wire.previewCsv(); event.target.value = ''; } }">
                        <label class="import-file-label">Elegir archivo CSV <input type="file" accept=".csv,text/csv" x-on:change="readFile($event)" aria-label="Elegir archivo CSV ficticio"></label>
                        <details class="import-paste"><summary>O pegar el contenido CSV</summary><textarea wire:model="csvText" rows="4" maxlength="100000" placeholder="referencia,cliente,concepto,emision,vencimiento,importe,estado" aria-label="Contenido CSV de facturas ficticias"></textarea><button type="button" wire:click="previewCsv" wire:loading.attr="disabled" wire:target="previewCsv">Revisar contenido</button></details>
                    </div>
                    <div class="import-format">
                        <p>Para cargar, pega primero esta cabecera exacta, separada por comas:<code>referencia,cliente,concepto,emision,vencimiento,importe,estado</code></p>
                        <p>Después, una factura por línea; por ejemplo:<code>EJ-901,Cliente de prueba,Servicio de muestra,{{ now()->toDateString() }},{{ now()->toDateString() }},1250.00,vigente</code></p>
                        <p>La emisión no puede ser futura; el vencimiento debe ser igual o posterior. Escribe el importe sin $ ni separador de miles, con punto decimal, y el estado como vigente o cancelada. Se usa PPD de muestra y no se cargan pagos. El CSV de «Exportar vista» es un reporte, no una plantilla de importación.</p>
                    </div>
                    <p class="import-privacy">Usa únicamente datos inventados: no subas CFDI, RFC, XML ni información de clientes reales. El archivo se lee en tu navegador; las filas confirmadas viven solo en esta sesión y no se guardan en PostgreSQL.</p>
                    @if ($importMessage)<p class="import-message" role="status">{{ $importMessage }}</p>@endif
                    @if ($importedCount > 0)
                        <div class="import-active"><span>{{ $importedCount }} {{ $importedCount === 1 ? 'factura importada' : 'facturas importadas' }} en esta sesión</span><button type="button" wire:click="resetImportedInvoices">Quitar importación de prueba</button></div>
                    @endif
                    @if ($importPreview)
                        <div class="import-preview" aria-label="Vista previa del CSV">
                            @if ($importPreview['error'])
                                <p class="import-error" role="alert">{{ $importPreview['error'] }}</p>
                            @else
                                <div class="import-preview-heading"><strong>Vista previa</strong><span>{{ count($importPreview['valid']) }} válidas · {{ count($importPreview['rows']) - count($importPreview['valid']) }} por corregir</span></div>
                                <div class="import-preview-scroll"><table><thead><tr><th>FILA / REFERENCIA</th><th>CLIENTE</th><th>IMPORTE</th><th>RESULTADO</th></tr></thead><tbody>
                                @foreach ($importPreview['rows'] as $previewRow)
                                    <tr><td>{{ $previewRow['line'] }} · {{ $previewRow['reference'] ?: 'Sin referencia' }}</td><td>{{ $previewRow['customer'] ?: '—' }}</td><td>${{ number_format($previewRow['amount_cents'] / 100, 2) }}</td><td>@if ($previewRow['errors'])<span class="import-row-error">{{ implode(' ', $previewRow['errors']) }}</span>@else<span class="import-row-ok">Lista · {{ $previewRow['status'] }}</span><small class="import-trace-hint">Traza prevista: registro de muestra; {{ $previewRow['status'] === 'cancelada' ? 'seguimiento detenido' : 'PPD sin cobros' }}.</small>@endif</td></tr>
                                @endforeach
                                </tbody></table></div>
                                <div class="import-impact"><div><span>Emitido vigente</span><strong>${{ number_format($totals['issued'] / 100, 2) }} → ${{ number_format(($totals['issued'] + $importPreview['impact']['issued']) / 100, 2) }}</strong></div><div><span>Saldo abierto</span><strong>${{ number_format($totals['open'] / 100, 2) }} → ${{ number_format(($totals['open'] + $importPreview['impact']['open']) / 100, 2) }}</strong></div><div><span>Saldo vencido</span><strong>${{ number_format($totals['overdue'] / 100, 2) }} → ${{ number_format(($totals['overdue'] + $importPreview['impact']['overdue']) / 100, 2) }}</strong></div></div>
                                <p class="import-preview-note">Cobrado no cambia: este CSV no incluye pagos. Al abrir una factura importada verás su detalle y seguimiento documental PPD ficticio.</p>
                                @if (count($importPreview['valid']) > 0)<button class="import-apply" type="button" wire:click="applyCsv" wire:loading.attr="disabled" wire:target="applyCsv">Agregar {{ count($importPreview['valid']) }} {{ count($importPreview['valid']) === 1 ? 'factura válida' : 'facturas válidas' }} a esta sesión →</button>@endif
                            @endif
                        </div>
                    @endif
                    </div>
                </section>
                </div>
                @endif

                <section id="facturas" class="panel invoice-panel">
                        <div class="panel-head"><div><div class="section-kicker">TRAZABILIDAD</div><h2>Facturas <span>{{ $invoiceCount }}</span></h2><p>Selecciona una factura para abrir su detalle y trazabilidad documental.</p></div><button id="importar" class="import-launch-button" type="button" wire:click="openImport">Importar facturas ficticias <span aria-hidden="true">↗</span></button></div>
                        <div class="table-toolbar"><label class="search-box"><span>⌕</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar folio o cliente" aria-label="Buscar folio o cliente"></label><label class="filter-wrap"><span>Estado</span><select wire:model.live="filter" aria-label="Filtrar por estado"><option value="todas">Todos</option><option value="pendiente">Pendiente</option><option value="parcial">Parcial</option><option value="vencida">Vencida</option><option value="pagada">Pagada</option><option value="cancelada">Cancelada</option></select></label><button class="export-button" type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv">Exportar vista CSV ↓</button></div>
                        <div class="table-scroll"><table><thead><tr><th>REFERENCIA / CLIENTE</th><th>EMISIÓN</th><th>IMPORTE</th><th>ESTADO</th><th></th></tr></thead><tbody>
                        @forelse ($rows as $row)
                            <tr wire:key="invoice-{{ $row['invoice']->id }}" wire:click="selectInvoice({{ $row['invoice']->id }})" class="invoice-row {{ $selectedInvoiceId === $row['invoice']->id ? 'selected-row' : '' }}">
                                <td><div class="invoice-ref">{{ $row['invoice']->reference }} @if ($row['invoice']->id < 0)<span class="import-tag">Importada</span>@endif</div><div class="customer-name">{{ $row['invoice']->customer->name }}</div></td>
                                <td class="muted-cell">{{ $row['invoice']->issued_on->format('d/m/Y') }}</td>
                                <td class="amount-cell">${{ number_format($row['invoice']->amount_cents / 100, 2) }}</td>
                                <td><span class="status {{ $row['state'] }}">{{ ucfirst($row['state']) }}</span></td>
                                <td><button class="row-button" type="button" wire:click.stop="selectInvoice({{ $row['invoice']->id }})" aria-label="Ver detalle de {{ $row['invoice']->reference }}">→</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-row">No hay facturas con ese filtro.</td></tr>
                        @endforelse
                        </tbody></table></div>
                        <div class="panel-foot">{{ $rows->count() }} de {{ $invoiceCount }} registros · Moneda MXN</div>
                </section>

                @if ($selected)
                    <div class="invoice-modal-backdrop" x-data x-trap.inert.noscroll="true" x-init="$nextTick(() => $refs.close.focus())" x-on:keydown.escape.window="$wire.closeInvoice()" wire:click.self="closeInvoice" wire:key="invoice-modal">
                    <section class="invoice-modal" role="dialog" aria-modal="true" aria-labelledby="invoice-modal-heading">
                        <div class="invoice-modal-shell">
                            <header class="invoice-modal-bar">
                                <div><span>FACTURA DE MUESTRA</span><h2 id="invoice-modal-heading">Detalle y seguimiento</h2></div>
                                <button type="button" class="invoice-modal-close" x-ref="close" wire:click="closeInvoice" aria-label="Cerrar detalle de factura">×</button>
                            </header>
                            <div class="invoice-modal-columns">
                                <section class="invoice-modal-detail" aria-label="Detalle de factura y cobro">
                            <div class="detail-header"><div><div class="section-kicker">DETALLE DE FACTURA</div><h2>{{ $selected['invoice']->reference }}</h2><p>{{ $selected['invoice']->customer->name }}</p></div><span class="status {{ $selected['state'] }}">{{ ucfirst($selected['state']) }}</span></div>
                            <div class="detail-concept"><small>CONCEPTO</small><strong>{{ $selected['invoice']->concept ?: 'Sin descripción' }}</strong></div>
                            <div class="detail-numbers"><div><span>Importe registrado</span><strong>${{ number_format($selected['invoice']->amount_cents / 100, 2) }}</strong></div><div><span>Pagos aplicados</span><strong>${{ number_format($selected['paid'] / 100, 2) }}</strong></div><div class="outstanding"><span>Saldo en el resumen</span><strong>${{ number_format($selected['open'] / 100, 2) }}</strong></div></div>
                            <div class="dates"><div>Emisión <strong>{{ $selected['invoice']->issued_on->format('d/m/Y') }}</strong></div><div>Vencimiento <strong>{{ $selected['invoice']->due_on->format('d/m/Y') }}</strong></div></div>
                            <div class="payment-list">
                                <h3>Movimientos vinculados</h3>
                                @forelse ($selected['movements'] as $movement)
                                    <div class="payment-item">
                                        <span><b>✓</b><span>{{ $movement['reference'] }} @if ($movement['simulated'])<em class="demo-payment-tag">Prueba</em>@endif<small>{{ $movement['paid_on'] }}</small></span></span>
                                        <strong>+${{ number_format($movement['amount_cents'] / 100, 2) }}</strong>
                                    </div>
                                @empty
                                    <p>No hay pagos registrados para esta factura.</p>
                                @endforelse
                            </div>
                            @if ($selected['state'] === 'pagada')
                                <section class="payment-complete-box" aria-label="Seguimiento de factura pagada">
                                    <span class="payment-complete-kicker">COBRO COMPLETADO</span>
                                    <h3>Esta factura ya quedó pagada</h3>
                                    <p>Su saldo es cero. Los movimientos quedan visibles arriba para consultar cómo se liquidó.</p>
                                    <ol class="payment-flow-steps">
                                        <li><span>01</span><div><strong>Registro de muestra</strong><small>Importe y cliente identificados</small></div></li>
                                        <li><span>02</span><div><strong>Abonos vinculados</strong><small>El total aplicado cubre la factura</small></div></li>
                                        <li class="payment-flow-next"><span>03</span><div><strong>Seguimiento documental · demo</strong><small>Consulta la otra sección de este modal; no se emiten CFDI ni complementos de pago.</small></div></li>
                                    </ol>
                                    @if ($demoPaymentCount > 0)
                                        <div class="demo-payment-reset-row">
                                            <span>Escenario de cobro simulado en esta sesión</span>
                                            <button type="button" wire:click="resetDemoPayments">Reiniciar escenario</button>
                                        </div>
                                    @endif
                                </section>
                            @elseif ($selected['state'] === 'cancelada')
                                <section class="payment-cancelled-box" aria-label="Registro cancelado">
                                    <span>REGISTRO CERRADO</span>
                                    <h3>Factura de muestra cancelada</h3>
                                    <p>Conservamos el registro para consulta. Aquí no se agregan abonos ni se ejecuta una nueva comprobación de datos.</p>
                                    @if ($demoPaymentCount > 0)
                                        <div class="demo-payment-reset-row">
                                            <span>{{ $demoPaymentCount }} {{ $demoPaymentCount === 1 ? 'abono de prueba' : 'abonos de prueba' }} en esta sesión</span>
                                            <button type="button" wire:click="resetDemoPayments">Reiniciar escenario</button>
                                        </div>
                                    @endif
                                </section>
                            @else
                                <section class="demo-payment-box" aria-label="Abonos de prueba">
                                    <span class="demo-payment-kicker">SEGUIMIENTO DE COBRO</span>
                                    <h3>Registrar abono de prueba</h3>
                                    <p>Prueba cómo cambia el saldo de esta factura. El registro original no se modifica.</p>
                                    <form wire:submit="registerDemoPayment">
                                        <div class="demo-payment-fields">
                                            <label for="demo-payment-amount">Importe en MXN
                                                <input id="demo-payment-amount" type="text" inputmode="decimal" autocomplete="off" placeholder="Ej. 500.00" wire:model="paymentAmount" aria-describedby="demo-payment-balance">
                                            </label>
                                            <label for="demo-payment-date">Fecha del abono
                                                <input id="demo-payment-date" type="date" min="{{ $selected['invoice']->issued_on->toDateString() }}" max="{{ $today->toDateString() }}" wire:model="paymentDate">
                                            </label>
                                        </div>
                                        <p id="demo-payment-balance" class="demo-payment-balance">Máximo disponible: ${{ number_format($selected['open'] / 100, 2) }}</p>
                                        @error('paymentAmount') <p class="demo-payment-error" role="alert">{{ $message }}</p> @enderror
                                        @error('paymentDate') <p class="demo-payment-error" role="alert">{{ $message }}</p> @enderror
                                        <button class="demo-payment-submit" type="submit" wire:loading.attr="disabled" wire:target="registerDemoPayment">Aplicar abono ficticio <span aria-hidden="true">→</span></button>
                                    </form>
                                    @if ($paymentMessage)
                                        <p class="demo-payment-message" role="status">{{ $paymentMessage }}</p>
                                    @endif
                                    @if ($demoPaymentCount > 0)
                                        <div class="demo-payment-reset-row">
                                            <span>{{ $demoPaymentCount }} {{ $demoPaymentCount === 1 ? 'abono de prueba' : 'abonos de prueba' }} en esta sesión</span>
                                            <button type="button" wire:click="resetDemoPayments">Reiniciar escenario</button>
                                        </div>
                                    @endif
                                    <p class="demo-payment-disclaimer">Solo demostración. No mueve dinero ni genera complementos fiscales.</p>
                                </section>
                                <div class="simulate-box"><div class="simulate-title"><span>◇</span><div><strong>Comprobación de datos · demo</strong><small>Solo prueba campos; no cambia el estado ni emite documentos.</small></div></div><label class="draft-label" for="draft-concept">Concepto para la comprobación</label><input id="draft-concept" class="draft-input" type="text" maxlength="160" wire:model="draftConcept" placeholder="Escribe un concepto de muestra"><p class="draft-note">Puedes corregirlo aquí; el registro original no cambia.</p><button type="button" wire:click="simulate" class="primary-button" wire:loading.attr="disabled">Comprobar datos de muestra <span>→</span></button><p class="sim-warning">No es una revisión fiscal: no hay timbre, UUID, SAT ni PAC.</p>
                                    @if ($simulation)
                                        <div class="sim-result {{ $simulation['approved'] ? 'sim-ok' : 'sim-error' }}"><strong>{{ $simulation['approved'] ? 'Datos completos' : 'Datos por corregir' }}</strong><p>{{ $simulation['message'] }}</p>@if ($simulation['issues'])<ul>@foreach ($simulation['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>@endif</div>
                                    @endif
                                </div>
                            @endif
                                </section>
                                <section class="invoice-modal-trace" aria-label="Trazabilidad documental">
                            <section class="document-trace-box" aria-label="Ficha documental de muestra">
                                <div class="document-trace-heading">
                                    <span>TRAZABILIDAD DOCUMENTAL</span>
                                    <h3>Ficha de esta factura</h3>
                                    <p>El estado del documento y el estado de cobro se leen por separado. Todo aquí es ficticio.</p>
                                </div>
                                <div class="document-trace-facts">
                                    <div>
                                        <small>MÉTODO DE PAGO · EJEMPLO</small>
                                        <strong>{{ $selected['document']['method'] }}</strong>
                                        <p>{{ $selected['document']['method_detail'] }}</p>
                                    </div>
                                    <div>
                                        <small>ESTADO DOCUMENTAL · DEMO</small>
                                        <strong>{{ $selected['document']['stage_label'] }}</strong>
                                        <p>{{ $selected['document']['stage_detail'] }}</p>
                                    </div>
                                </div>
                                <h4>Línea de tiempo de muestra</h4>
                                <ol class="document-timeline">
                                    @foreach ($selected['document']['events'] as $event)
                                        <li class="document-event document-event-{{ $event['kind'] }}">
                                            <span class="document-event-mark" aria-hidden="true"></span>
                                            <div><small>{{ $event['date'] }}</small><strong>{{ $event['title'] }}</strong><p>{{ $event['detail'] }}</p></div>
                                        </li>
                                    @endforeach
                                </ol>
                                <p class="document-trace-disclaimer">Sin XML, timbre, UUID, PAC ni envío al SAT. No es una validación fiscal.</p>
                            </section>
                                </section>
                            </div>
                        </div>
                    </section>
                    </div>
                @endif
            @endif

            <footer class="footer"><span>Pulso Fiscal · Proyecto de portafolio</span><span>Sin datos reales · Sin validez fiscal</span></footer>
        </div>
    </main>
</div>
