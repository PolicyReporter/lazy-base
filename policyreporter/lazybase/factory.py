import psycopg2
from . import handle as _handle

def pyscopg2(host, port, dbname, user, password, application_name):
    return _handle.Handle(psycopg2.connect(host=host, port=port, dbname=dbname, user=user, password=password, application_name=application_name))
