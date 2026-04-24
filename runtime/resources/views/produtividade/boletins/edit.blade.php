@extends('layouts.app')

@section('title', 'Editar BO | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Editar boletim de ocorrencia</h1>
            <p class="muted" style="margin: 6px 0 0;">SPJ {{ $boletim->spj ?: 'sem identificador SPJ' }} · {{ $boletim->cartorio ? str_pad((string) $boletim->cartorio->number, 3, '0', STR_PAD_LEFT).' - '.$boletim->cartorio->name : 'Sem cartorio' }}</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('produtividade.boletins.index', ['cartorio_id' => request('cartorio_id'), 'year' => request('year'), 'month' => request('month')]) }}">Voltar</a>
        </div>
    </div>

    <section class="card">
        <form method="POST" action="{{ route('produtividade.boletins.update', $boletim) }}" class="grid">
            @csrf
            @method('PATCH')

            <input type="hidden" name="cartorio_id" value="{{ request('cartorio_id') }}">
            <input type="hidden" name="year" value="{{ request('year') }}">
            <input type="hidden" name="month" value="{{ request('month') }}">

            <div class="form-grid">
                <div class="field">
                    <label for="is_flagrante">Tipo</label>
                    <select id="is_flagrante" name="is_flagrante" required>
                        <option value="1" @selected($boletim->is_flagrante)>Flagrante</option>
                        <option value="0" @selected(! $boletim->is_flagrante)>Nao-flagrante</option>
                    </select>
                </div>
                <div class="field">
                    <label for="lavrado_unidade">Lavrado</label>
                    <select id="lavrado_unidade" name="lavrado_unidade" required>
                        <option value="DDM" @selected($boletim->lavrado_unidade?->value === 'DDM')>DDM</option>
                        <option value="OUTRAS_UNIDADES" @selected($boletim->lavrado_unidade?->value !== 'DDM')>Outras Unidades</option>
                    </select>
                </div>
                <div class="field">
                    <label for="mpu_numero">MPU</label>
                    <input id="mpu_numero" name="mpu_numero" type="text" value="{{ old('mpu_numero', $boletim->mpu_numero) }}">
                </div>
                <div class="field">
                    <label for="mpu_decisao">Decisao MPU</label>
                    <select id="mpu_decisao" name="mpu_decisao">
                        <option value="" @selected(blank(old('mpu_decisao', $boletim->mpu_decisao)))>Nao informado</option>
                        <option value="DEFERIDA" @selected(old('mpu_decisao', $boletim->mpu_decisao) === 'DEFERIDA')>Deferida</option>
                        <option value="INDEFERIDA" @selected(old('mpu_decisao', $boletim->mpu_decisao) === 'INDEFERIDA')>Indeferida</option>
                    </select>
                </div>
                <div class="field">
                    <label for="num_ip">Nº IP</label>
                    <input id="num_ip" name="num_ip" type="text" value="{{ old('num_ip', $boletim->num_ip) }}">
                </div>
                <div class="field">
                    <label for="num_ipe">Nº IP-e</label>
                    <input id="num_ipe" name="num_ipe" type="text" value="{{ old('num_ipe', $boletim->num_ipe) }}">
                </div>
                <div class="field">
                    <label for="num_cnj">Nº CNJ</label>
                    <input id="num_cnj" name="num_cnj" type="text" value="{{ old('num_cnj', $boletim->num_cnj) }}">
                </div>
            </div>

            <div class="field">
                <label for="notes">Observacoes</label>
                <textarea id="notes" name="notes" rows="4">{{ old('notes', $boletim->notes) }}</textarea>
            </div>

            <div class="field" style="display:flex; align-items:center; gap:8px;">
                <input id="despacho_fundamentado" name="despacho_fundamentado" type="checkbox" value="1" @checked((bool) old('despacho_fundamentado', $boletim->despacho_fundamentado))>
                <label for="despacho_fundamentado" style="margin:0;">BO com MPU ja despachado fundamentado (nao gera IP, salvo ordem judicial)</label>
            </div>

            <div class="field" style="display:flex; align-items:center; gap:8px;">
                <input id="encaminhado_outra_unidade" name="encaminhado_outra_unidade" type="checkbox" value="1" @checked((bool) old('encaminhado_outra_unidade', $boletim->encaminhado_outra_unidade))>
                <label for="encaminhado_outra_unidade" style="margin:0;">BO encaminhado para outra unidade (nao e atribuicao da DDM — nao gera IP aqui)</label>
            </div>
            <div class="field" id="encaminhado_para_unidade_field" style="{{ old('encaminhado_outra_unidade', $boletim->encaminhado_outra_unidade) ? '' : 'display:none;' }}">
                <label for="encaminhado_para_unidade">Unidade destinataria (opcional)</label>
                <input id="encaminhado_para_unidade" name="encaminhado_para_unidade" type="text" maxlength="200"
                       value="{{ old('encaminhado_para_unidade', $boletim->encaminhado_para_unidade) }}"
                       placeholder="Ex: 2DP, DECAP, DP de Pinheiros...">
            </div>
            <script>
                document.getElementById('encaminhado_outra_unidade').addEventListener('change', function () {
                    document.getElementById('encaminhado_para_unidade_field').style.display = this.checked ? '' : 'none';
                });
            </script>

            <div class="actions">
                <button type="submit">Salvar alteracoes</button>
                <a class="btn secondary" href="{{ route('produtividade.boletins.index', ['cartorio_id' => request('cartorio_id'), 'year' => request('year'), 'month' => request('month')]) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
