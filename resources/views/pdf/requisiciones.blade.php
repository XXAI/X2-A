<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style type="text/css">
		@page {
            margin-top: 10.3em;
            margin-left: 1.6em;
            margin-right: 0.6em;
            margin-bottom: 1.3em;
        }
        table{
        	width:100%;
        	border-collapse: collapse;
        }
        
        .misma-linea{
        	display: inline-block;
        }
		.cuerpo{
			font-size: 8pt;
			font-family: arial, sans-serif;
		}
		.titulo1{
			font-weight: bold;
			font-family: arial, sans-serif;
			font-size: 14;
		}
		.titulo2{
			font-weight: bold;
			font-family: arial, sans-serif;
			font-size: 7pt;
		}
		.titulo3{
			font-weight: bold;
			font-family: arial, sans-serif;
			font-size: 8pt;
		}
		.titulo4{
			font-weight: bold;
			font-family: arial, sans-serif;
			font-size: 11;
		}
		.texto{
			font-family: arial, sans-serif;
			font-size: 10pt;
		}
		.negrita{
			font-weight: bold;
		}
		.linea-firma{
			border-bottom: 1 solid #000000;
		}
		.texto-medio{
			vertical-align: middle;
		}
		.texto-centro{
			text-align: center;
		}
		.texto-derecha{
			text-align: right !important;
		}
		.texto-izquierda{
			text-align: left;
		}
		.encabezado-tabla{
			font-family: arial, sans-serif;
			font-size: 5pt;
			text-align: center;
			vertical-align: middle;
		}
		.tabla-datos{
			width: 100%;
		}
		.tabla-datos td,
		.tabla-datos th{
			border: thin solid #000000;
			border-collapse: collapse;
			padding:1;
		}
		.subtitulo-tabla{
			font-weight: bold;
			background-color: #DDDDDD;
		}
		.subsubtitulo-tabla{
			font-weight: bold;
			background-color: #EFEFEF;
		}
		.nota-titulo{
			font-family: arial, sans-serif;
			font-size:8;
			font-weight: bold;
		}
		.nota-contenido{
			font-family: arial, sans-serif;
			font-size:8;
		}
		.imagen{
			vertical-align: top;
		}

		.imagen.izquierda{
			text-align: left;
		}
		.imagen.derecha{
			text-align: right;
		}
		.imagen.centro{
			text-align: center;
		}
		.sin-bordes{
			border: none;
			border-collapse: collapse;
		}
		.header,.footer {
		    width: 100%;
		    text-align: center;
		    position: fixed;
		}
		.header {
		    top: -15.0em;
		}
		.footer {
		    bottom: 0px;
		}
		.pagenum:before {
		    content: counter(page);
		}
		.naranja{
			color:rgb(237,125,49);
		}
	</style>
</head>
<body class="cuerpo">
	<div class="header">
		<table>
			<tr>
				<td class="imagen izquierda">
					<img src="{{ public_path().'/img/LogoFederal.png' }}" height="45">
				</td>
				<td class="imagen centro">
					<img src="{{ public_path().'/img/MxSnTrabInf.jpg' }}" height="45">
				</td>
				<td class="imagen centro">
					<img src="{{ public_path().'/img/EscudoGobiernoChiapas.png' }}" height="45">
				</td>
				<td class="imagen derecha">
					<img src="{{ public_path().'/img/LogoInstitucional.png' }}" height="45">
				</td>
			</tr>
			<tr><td colspan="4" class="titulo2" align="center">INSTITUTO DE SALUD</td></tr>
			<tr><td colspan="4" class="titulo2" align="center">{{$unidad}}</td></tr>
			<tr><td colspan="4" class="titulo2" align="center">REQUISICICIÓN DE INSUMOS DE MEDICAMENTOS</td></tr>
			<tr><td colspan="4" class="titulo2" align="center">ANEXO DEL ACTA No. {{$acta->folio}} DE FECHA {{$acta->fecha[2]}} DE {{$acta->fecha[1]}} DEL {{$acta->fecha[0]}}</td></tr>
		</table>
	</diV>
	@foreach($acta->requisiciones as $index => $requisicion)
	@if($index > 0)
		<div style="page-break-after:always;"></div>
	@endif
	<table width="100%">
		<thead>
			<tr class="tabla-datos">
				<th colspan="6" class="encabezado-tabla" align="center">REQUISICION DE {{($requisicion->tipo_requisicion == 1)?'MEDICAMENTOS CAUSES':(($requisicion->tipo_requisicion == 2)?'MEDICAMENTOS NO CAUSES':'MATERIAL DE CURACIÓN')}} </th>
			</tr>
			<tr class="tabla-datos">
				<th rowspan="2" width="20%" class="encabezado-tabla">REQUISICIÓN DE COMPRA</th>
				<th rowspan="2" width="20%" class="encabezado-tabla">UNIDAD MÉDICA EN DESABASTO</th>
				<th colspan="4" class="encabezado-tabla">DATOS</th>
			</tr>
			<tr class="tabla-datos">
				<th width="10%" class="encabezado-tabla">PEDIDO</th>
				<th width="7%" class="encabezado-tabla">LOTES A <br>ADJUDICAR</th>
				<th width="8%" class="encabezado-tabla">EMPRESA <br>ADJUDICADA EN <br>LICITACIÓN</th>
				<th width="35%" class="encabezado-tabla">DIAS DE SURTIMIENTO</th>
			</tr>
			<tr class="tabla-datos">
				<td class="encabezado-tabla">No. {{$requisicion->numero}}</td>
				<td class="encabezado-tabla">{{$unidad}}</td>
				<td class="encabezado-tabla">{{$requisicion->pedido}}</td>
				<td class="encabezado-tabla">{{$requisicion->lotes}}</td>
				<td class="encabezado-tabla">{{$empresa}}</td>
				<td class="encabezado-tabla">{{$requisicion->dias_surtimiento}}</td>
			</tr>
		</thead>
	</table>
	<table width="100%">
		<thead>
			<tr class="tabla-datos">
				<th class="encabezado-tabla" width="10%">No. DE LOTE</th>
				<th class="encabezado-tabla" width="10%">CLAVE</th>
				<th class="encabezado-tabla" width="30%">DESCRIPCIÓN DEL INSUMO</th>
				<th class="encabezado-tabla" width="15%">CANTIDAD</th>
				<th class="encabezado-tabla" width="15%">UNIDAD DE MEDIDA</th>
				<th class="encabezado-tabla" width="10%">PRECIO <br>UNITARIO</th>
				<th class="encabezado-tabla" width="10%">TOTAL</th>
			</tr>
		</thead>
		<tbody>
		@foreach($requisicion->insumos as $indice => $insumo)
			<tr class="tabla-datos">
				<td class="encabezado-tabla">{{$insumo->lote}}</td>
				<td class="encabezado-tabla">{{$insumo->clave}}</td>
				<td class="encabezado-tabla">{{$insumo->descripcion}}</td>
				<td class="encabezado-tabla">{{number_format($insumo->pivot->cantidad)}}</td>
				<td class="encabezado-tabla">{{$insumo->unidad}}</td>
				<td class="encabezado-tabla">$ {{number_format($insumo->precio,2)}}</td>
				<td class="encabezado-tabla">$ {{number_format($insumo->pivot->total,2)}}</td>
			</tr>
		@endforeach
		</tbody>
		<tfoot>
			<tr class="tabla-datos">
				<td colspan="4" rowspan="3"></td>
				<th colspan="2" class="encabezado-tabla texto-derecha">SUBTOTAL</th>
				<td class="encabezado-tabla">$ {{number_format($requisicion->sub_total,2)}}</td>
			</tr>
			<tr class="tabla-datos">
				<th colspan="2" class="encabezado-tabla texto-derecha">IVA</th>
				<td class="encabezado-tabla">SI IVA</td>
			</tr>
			<tr class="tabla-datos">
				<th colspan="2" class="encabezado-tabla texto-derecha">GRAN TOTAL</th>
				<td class="encabezado-tabla">$ {{number_format($requisicion->gran_total,2)}}</td>
			</tr>
		</tfoot>
	</table>
	<table width="100%">
		<tbody>
			<tr class="tabla-datos">
				<th class="encabezado-tabla">SOLICITA</th>
				<th class="encabezado-tabla">DIRECCIÓN O UNIDAD</th>
				<th width="50%" rowspan="3"></th>
			</tr>
			<tr class="tabla-datos">
				<td class="encabezado-tabla">{{$requisicion->firma_solicita}}</td>
				<td class="encabezado-tabla">{{$requisicion->firma_director}}</td>
			</tr>
			<tr class="tabla-datos">
				<td class="encabezado-tabla">{{mb_strtoupper($requisicion->cargo_solicita,'UTF-8')}}</td>
				<td class="encabezado-tabla">DIRECTOR DE LA JURISDICCIÓN O UNIDAD MÉDICA</td>
			</tr>
		</tbody>
	</table>
	@endforeach
</body>
</html>