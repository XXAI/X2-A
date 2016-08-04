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
			font-size: 13;
		}
		.titulo3{
			font-weight: bold;
			font-family: arial, sans-serif;
			font-size: 12;
		}
		.titulo4{
			font-weight: bold;
			font-family: arial, sans-serif;
			font-size: 11;
		}
		.texto{
			font-family: arial, sans-serif;
			font-size: 10;
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
			text-align: right;
		}
		.texto-izquierda{
			text-align: left;
		}
		.encabezado-tabla{
			font-family: arial, sans-serif;
			font-size: 8;
			font-weight: normal;
			text-align: center;
			vertical-align: middle;
			color: #FFFFFF;
			background-color: #0070C0;
		}
		.tabla-datos{
			width: 100%;
		}
		.tabla-datos td,
		.tabla-datos th{
			border: 1 solid #000000;
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
	</style>
</head>
<body class="cuerpo">
	<div class="header">
		<table>
			<tr>
				<td rowspan="5" class="imagen izquierda"><img src="{{ public_path().'/img/LogoFederal.png' }}" width="125"></td>
				<td></td>
				<td rowspan="5" class="imagen derecha"><img src="{{ public_path().'/img/LogoInstitucional.png' }}" width="125"></td>
			</tr>
			<tr><td class="titulo2" align="center">INSTITUTO DE SALUD</td></tr>
			<tr><td class="titulo2" align="center">nombre de clues</td></tr>
			<tr><td class="titulo3" align="center">REQUISICICIÓN DE INSUMOS DE MEDICAMENTOS</td></tr>
			<tr><td class="titulo3" align="center">ANEXO DEL ACTA No. {{$acta->folio}} DE FECHA {{$acta->fecha}}</td></tr>
			<tr><td class="titulo3" align="center">REQUISICION DE MEDICAMENTOS CAUSES </td></tr>
		</table>
	</diV>
	<table width="100%">
		<thead>
			<tr class="tabla-datos" height="50">
				<td class="encabezado-tabla">NIVEL</td>
				<td class="encabezado-tabla">INDICADOR</td>
				<td class="encabezado-tabla">META<br>PROGRAMADA</td>
				<td class="encabezado-tabla">META<br>MODIFICADA</td>
				<td class="encabezado-tabla">AVANCES DEL MES</td>
				<td class="encabezado-tabla">AVANCE ACUMULADO</td>
				<td class="encabezado-tabla">% DE AVANCE ACUMULADO</td>
				<td class="encabezado-tabla">% DE AVANCE MODIFICADO</td>
				<td class="encabezado-tabla">ANALISIS DE RESULTADOS 	ACUMULADO</td>
				<td class="encabezado-tabla">JUSTIFICACIÓN ACUMULADA</td>
			</tr>
		</thead>
	</table>
	<table width="100%">
		<thead>
		<tr class="tabla-datos" height="50">
			<td class="encabezado-tabla">NIVEL</td>
			<td class="encabezado-tabla">INDICADOR</td>
			<td class="encabezado-tabla">META<br>PROGRAMADA</td>
			<td class="encabezado-tabla">META<br>MODIFICADA</td>
			<td class="encabezado-tabla">AVANCES DEL MES</td>
			<td class="encabezado-tabla">AVANCE ACUMULADO</td>
			<td class="encabezado-tabla">% DE AVANCE ACUMULADO</td>
			<td class="encabezado-tabla">% DE AVANCE MODIFICADO</td>
			<td class="encabezado-tabla">ANALISIS DE RESULTADOS 	ACUMULADO</td>
			<td class="encabezado-tabla">JUSTIFICACIÓN ACUMULADA</td>
		</tr>
		</thead>
		<tbody>
		</tbody>
	</table>
	<table style="page-break-inside:avoid;">
		<tr class="negrita" height="20">
			<td width="10%"></td>
			<td align="center">RESPONSABLE DE LA INFORMACIÓN</td>
			<td width="10%"></td>
			<td align="center">LIDER DEL PROYECTO</td>
			<td width="10%"></td>
		</tr>
		<tr>
			<td></td>
			<td height="40" class="linea-firma"></td>
			<td>&nbsp;</td>
			<td class="linea-firma"></td>
			<td></td>
		</tr>
		<tr class="negrita" height="20">
			<td></td>
			<td align="center"></td>
			<td></td>
			<td align="center"></td>
			<td></td>
		</tr>
		<tr class="negrita" height="20">
			<td></td>
			<td align="center"></td>
			<td></td>
			<td align="center"></td>
			<td></td>
		</tr>
	</table>

	<div style="page-break-after:always;"></div>

	<table>
		<tr height="20" class="texto">
			<td width="100" class="texto-derecha">Información: </td>
			<td class="negrita">Jurisdiccional</td>
			<td colspan="6"></td>
		</tr>
		<tr height="15"><td colspan="8">&nbsp;</td></tr>
	</table>

	<table>
		<thead>
		<tr class="tabla-datos" height="40">
			<td width="60" class="encabezado-tabla">NIVEL</td>
			<td width="150" class="encabezado-tabla">INDICADOR</td>
			<td width="90" class="encabezado-tabla">META PROGRAMADA</td>
			<td width="90" class="encabezado-tabla">META MODIFICADA</td>
			<td width="90" class="encabezado-tabla">AVANCES DEL MES</td>
			<td width="90" class="encabezado-tabla">AVANCE ACUMULADO</td>
			<td width="80" class="encabezado-tabla">% DE AVANCE ACUMULADO</td>
			<td width="80" class="encabezado-tabla">% DE AVANCE MODIFICADO</td>
		</tr>
		</thead>
	</table>
</body>
</html>