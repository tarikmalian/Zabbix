## Autor: Janssen dos Reis Lima / janssenreislima@gmail.com>
## Ultima atualizacao: 18/02/2016
## Observacoes: Este script eh executado automaticamente apos a abertura do ticket no GLPI
## -------------------------------------------------------------------------------------------------
## Atualizado para funcionar com Zabbix 4.4 por Tarik Malian em 22/01/2020 / carlostarik@hotmail.com


from zabbix_api import ZabbixAPI
import sys
 
server = "http://localhost/zabbix"
username = "Admin"             
password = "zabbix"     
 
conexao = ZabbixAPI(server = server)
conexao.login(username, password)

reconhecer_evento = conexao.event.acknowledge({"eventids": sys.argv[1], "action": 2,  "message": "Ticket " + str(sys.argv[2]) + " criado no GLPI."})
escrever_mensagem = conexao.event.acknowledge({"eventids": sys.argv[1], "action": 4,  "message": "Ticket " + str(sys.argv[2]) + " criado no GLPI."})
