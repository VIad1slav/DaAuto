"""Export trusted root certificates from the Windows store to a PEM file for PHP curl.

Portable PHP has no certificate list of its own, so without this file it cannot
open https://api.telegram.org. Run by test-bot.bat on every start.
"""
import ssl
import sys

SERVER_AUTH = '1.3.6.1.5.5.7.3.1'

pems = []
for der, encoding, trust in ssl.enum_certificates('ROOT'):
    if encoding == 'x509_asn' and (trust is True or SERVER_AUTH in trust):
        pems.append(ssl.DER_cert_to_PEM_cert(der))

if not pems:
    sys.exit('no certificates found')
with open(sys.argv[1], 'w', encoding='ascii') as f:
    f.write(''.join(dict.fromkeys(pems)))
