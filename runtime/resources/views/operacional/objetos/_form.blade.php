{{-- Partial de formulário de objeto apreendido. Usado nos modais de cadastro e edição. --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">

    {{-- RDO / Ano / Lacre --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">RDO Nº</label>
        <input type="text" name="rdo_num" maxlength="30" style="width:100%;" placeholder="Ex.: TA7587">
    </div>
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Ano</label>
        <input type="number" name="ano" min="2000" max="2100" style="width:100%;" placeholder="{{ date('Y') }}">
    </div>
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Lacre</label>
        <input type="text" name="lacre" maxlength="40" style="width:100%;" placeholder="Número do lacre">
    </div>
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Lacre IC</label>
        <input type="text" name="lacre_ic" maxlength="40" style="width:100%;" placeholder="Lacre do IC, se houver">
    </div>

    {{-- IP / TC --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">IP/TC — DDM</label>
        <input type="text" name="ip_tc_ddm" maxlength="60" style="width:100%;" placeholder="IP ou TC da DDM">
    </div>
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">IP Externo</label>
        <input type="text" name="ip_externo" maxlength="60" style="width:100%;" placeholder="IP delegacia de origem">
    </div>

    {{-- Tipo / Descrição --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Tipo de Objeto</label>
        <input type="text" name="tipo_objeto" maxlength="120" style="width:100%;" placeholder="Ex.: Arma de fogo, Celular, Veículo…">
    </div>
    <div style="display:grid; grid-template-columns:3fr 1fr; gap:8px;">
        <div>
            <label style="display:block; font-size:.85rem; margin-bottom:4px;">Quantidade</label>
            <input type="number" name="quantidade" min="1" max="9999" value="1" style="width:100%;">
        </div>
        <div>
            <label style="display:block; font-size:.85rem; margin-bottom:4px;">Unidade</label>
            <input type="text" name="unidade" maxlength="30" style="width:100%;" placeholder="un / g / ml">
        </div>
    </div>
    <div style="grid-column: 1 / -1;">
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Descrição <span style="color:#c0392b">*</span></label>
        <textarea name="objeto" required maxlength="5000" rows="3" style="width:100%;" placeholder="Descrição completa do objeto apreendido…"></textarea>
    </div>

    {{-- Marca / Modelo / Cor / Série --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Marca</label>
        <input type="text" name="marca" maxlength="80" style="width:100%;" placeholder="Fabricante / marca">
    </div>
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Modelo</label>
        <input type="text" name="modelo" maxlength="80" style="width:100%;">
    </div>
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Cor</label>
        <input type="text" name="cor" maxlength="50" style="width:100%;">
    </div>
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Número de Série</label>
        <input type="text" name="numero_serie" maxlength="80" style="width:100%;">
    </div>

    {{-- Custódia --}}
    <div style="grid-column: 1 / -1; border-top:1px solid var(--color-border,#ddd); padding-top:14px; margin-top:4px;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Situação <span style="color:#c0392b">*</span></label>
                <select name="situacao" required style="width:100%;">
                    @foreach ($situacoes as $s)
                        <option value="{{ $s }}" {{ $s === 'Em Custódia' ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Local de Custódia</label>
                <select name="local_custodia_id" style="width:100%;">
                    <option value="">— Não informado —</option>
                    @foreach ($locais as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Caixa</label>
                <input type="text" name="caixa" maxlength="30" style="width:100%;" placeholder="Número ou código da caixa">
            </div>
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Laudo Pericial</label>
                <input type="text" name="laudo" maxlength="80" style="width:100%;" placeholder="Número do laudo">
            </div>
        </div>
    </div>

    {{-- IC --}}
    <div style="grid-column: 1 / -1; border-top:1px solid var(--color-border,#ddd); padding-top:14px; margin-top:4px;">
        <p style="margin:0 0 12px; font-size:.85rem; font-weight:700;">Instituto de Criminalística (IC)</p>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Remessa ao IC</label>
                <input type="text" name="ic_remessa" maxlength="60" style="width:100%;" placeholder="Data ou número de ofício">
            </div>
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Retorno do IC</label>
                <input type="text" name="ic_retorno" maxlength="60" style="width:100%;" placeholder="Data ou número de retorno">
            </div>
        </div>
    </div>

    {{-- Destinação --}}
    <div style="grid-column: 1 / -1; border-top:1px solid var(--color-border,#ddd); padding-top:14px; margin-top:4px;">
        <p style="margin:0 0 12px; font-size:.85rem; font-weight:700;">Destinação</p>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Solicitante</label>
                <input type="text" name="dest_solicitado" maxlength="80" style="width:100%;">
            </div>
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Data da Solicitação</label>
                <input type="date" name="dest_data_solicitado" style="width:100%;">
            </div>
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Status</label>
                <select name="dest_status" style="width:100%;">
                    <option value="">—</option>
                    @foreach ($destStatus as $ds)
                        <option value="{{ $ds }}">{{ $ds }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Autorizado por</label>
                <input type="text" name="dest_autorizado" maxlength="80" style="width:100%;">
            </div>
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Data da Autorização</label>
                <input type="date" name="dest_data_autorizado" style="width:100%;">
            </div>
            <div>
                <label style="display:block; font-size:.85rem; margin-bottom:4px;">Data de Conclusão</label>
                <input type="date" name="dest_data" style="width:100%;">
            </div>
        </div>
    </div>

    {{-- Observações --}}
    <div style="grid-column: 1 / -1;">
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Observações</label>
        <textarea name="observacoes" rows="3" maxlength="5000" style="width:100%;"></textarea>
    </div>

</div>

@if ($errors->any())
    <ul style="color:#c0392b; margin-top:16px; padding-left:20px;">
        @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
@endif
