<!-- Modal para atribuição de período de substituição da Delegada DDM por Delegado Externo -->
<div id="modal-substituicao-ddm" class="grom-overlay">
    <div class="card" style="width:480px; max-width:96vw;">
        <h2 style="margin-top:0;">Substituição da Delegada DDM</h2>
        <form method="POST" action="{{ route('escalas.substituicao-ddm.store') }}">
            @csrf
            <div class="field"><label>Delegado Externo Substituto</label>
                <select name="delegado_externo_id" required>
                    <option value="">Selecionar...</option>
                    @foreach ($delegadosExternos as $delegado)
                        <option value="{{ $delegado->id }}">{{ $delegado->short_name ?? $delegado->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label>Período de Substituição</label>
                <input type="date" name="data_inicio" required> até
                <input type="date" name="data_fim" required>
            </div>
            <div class="field"><label>Motivo</label>
                <select name="motivo" required>
                    <option value="Férias">Férias</option>
                    <option value="Licença Prêmio">Licença Prêmio</option>
                    <option value="Licença Saúde">Licença Saúde</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="actions" style="margin-top:14px; justify-content:flex-end;">
                <button type="button" data-close-modal="modal-substituicao-ddm">Cancelar</button>
                <button type="submit">Salvar</button>
            </div>
        </form>
    </div>
</div>
