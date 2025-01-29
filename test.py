import policyreporter.lazybase.factory as _factory
import re,json


handle = _factory.pyscopg2(
    host='host.docker.internal',
    port=5432,
    dbname='policyr',
    application_name='php_portal',
    user='policyr',
    password='Nope!'
)

def enbiggenArizona(row):
    if row['name'] == 'Arizona':
        row['name'] = 'Arizoooona'
    return row

print(json.dumps(
    handle.run(
    '''
    SELECT states.id, states.name, JSON_AGG(DISTINCT company.companyname) as companies
    FROM
            states
        JOIN companypresence
            ON states.id = companypresence.stateid
        JOIN company
            ON companypresence.companyid = company.id
    WHERE states.id IN(:ids) AND states.name ILIKE :name AND company.companyname IN(:companyname)
    GROUP BY states.id, states.name
    ''',
    {
        'ids': ['AZ', 'AK', 'AR'],
        'name': 'Ar%',
        'companyname': ['BCBS Arizona', 'Aetna'],
    }
    ).map(enbiggenArizona).fetchall(),
    indent=4
))

