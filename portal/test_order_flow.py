#!/usr/bin/env python3
import sqlite3
import os

DB='/tmp/test_collagendirect.db'
if os.path.exists(DB): os.remove(DB)
con=sqlite3.connect(DB)
con.row_factory=sqlite3.Row
c=con.cursor()

# Minimal schema based on PHP expectations
c.executescript('''
CREATE TABLE users (
  id TEXT PRIMARY KEY,
  npi TEXT,
  sign_name TEXT,
  sign_title TEXT,
  password_hash TEXT,
  sign_date TEXT,
  updated_at TEXT
);

CREATE TABLE patients (
  id TEXT PRIMARY KEY,
  user_id TEXT,
  first_name TEXT,
  last_name TEXT,
  dob TEXT,
  mrn TEXT,
  phone TEXT,
  email TEXT,
  address TEXT,
  city TEXT,
  state TEXT,
  zip TEXT,
  id_card_path TEXT,
  id_card_mime TEXT,
  ins_card_path TEXT,
  ins_card_mime TEXT,
  aob_path TEXT,
  aob_signed_at TEXT,
  aob_ip TEXT,
  created_at TEXT,
  updated_at TEXT
);

CREATE TABLE products (
  id INTEGER PRIMARY KEY,
  name TEXT,
  size TEXT,
  uom TEXT,
  price_admin REAL,
  cpt_code TEXT,
  active INTEGER DEFAULT 1
);

CREATE TABLE orders (
  id TEXT PRIMARY KEY,
  patient_id TEXT,
  user_id TEXT,
  product TEXT,
  product_id INTEGER,
  product_price REAL,
  status TEXT,
  shipments_remaining INTEGER,
  delivery_mode TEXT,
  payment_type TEXT,
  wound_location TEXT,
  wound_laterality TEXT,
  wound_notes TEXT,
  shipping_name TEXT,
  shipping_phone TEXT,
  shipping_address TEXT,
  shipping_city TEXT,
  shipping_state TEXT,
  shipping_zip TEXT,
  sign_name TEXT,
  sign_title TEXT,
  signed_at TEXT,
  created_at TEXT,
  updated_at TEXT,
  icd10_primary TEXT,
  icd10_secondary TEXT,
  wound_length_cm REAL,
  wound_width_cm REAL,
  wound_depth_cm REAL,
  wound_type TEXT,
  wound_stage TEXT,
  last_eval_date TEXT,
  start_date TEXT,
  frequency_per_week INTEGER,
  qty_per_change INTEGER,
  duration_days INTEGER,
  refills_allowed INTEGER,
  additional_instructions TEXT,
  rx_note_name TEXT,
  rx_note_mime TEXT,
  rx_note_path TEXT,
  cpt TEXT
);
''')
con.commit()

# Insert test user with NPI
user_id='user-1'
c.execute("INSERT INTO users (id,npi,sign_name,sign_title) VALUES (?,?,?,?)",(user_id,'1234567890','Dr Tester','MD'))
# Insert a product
c.execute("INSERT INTO products (id,name,price_admin,active) VALUES (?,?,?,?)",(1,'Test Product',199.99,1))
con.commit()

# Simulate creating a patient (patient.save behavior)
import secrets,datetime
pid=secrets.token_hex(16)
mrn='CD-'+datetime.date.today().strftime('%Y%m%d')+'-'+secrets.token_hex(2)[:4].upper()
now=datetime.datetime.utcnow().isoformat()

c.execute("INSERT INTO patients (id,user_id,first_name,last_name,dob,mrn,city,state,phone,email,address,zip,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
          (pid,user_id,'Alice','Smith','1970-01-01',mrn,'','', '5555555555','alice@example.com','123 Main St','12345',now,now))
con.commit()

# Now simulate order.create SQL (use self_pay to avoid insurance/AOB requirement)
import uuid
oid=secrets.token_hex(16)
product_id=1
# fetch product
prod=c.execute('SELECT id,name,price_admin,cpt_code FROM products WHERE id=? AND active=1',(product_id,)).fetchone()
if not prod:
    raise SystemExit('product not found')

# check user npi
ud=c.execute('SELECT npi,sign_name,sign_title FROM users WHERE id=?',(user_id,)).fetchone()
if not ud or not ud['npi']:
    raise SystemExit('user NPI required')

# prepare order data
payment_type='self_pay'
delivery_mode='patient'
sign_name='Dr Tester'
sign_title='MD'
ack_sig=1
icd10_primary='L97.412'
last_eval_date='2025-10-20'
wlen=2.5
wwid=1.2
wdep=None
start_date=datetime.date.today().isoformat()
freq_per_week=3
qty_per_change=1
duration_days=30
refills_allowed=0
additional_instructions='Test'

c.execute('''INSERT INTO orders (id,patient_id,user_id,product,product_id,product_price,status,shipments_remaining,delivery_mode,payment_type,
         wound_location,wound_laterality,wound_notes,
         shipping_name,shipping_phone,shipping_address,shipping_city,shipping_state,shipping_zip,
         sign_name,sign_title,signed_at,created_at,updated_at,
         icd10_primary,icd10_secondary,wound_length_cm,wound_width_cm,wound_depth_cm,
         wound_type,wound_stage,last_eval_date,start_date,frequency_per_week,qty_per_change,duration_days,refills_allowed,additional_instructions,cpt)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,datetime('now'),datetime('now'),datetime('now'),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
          (oid,pid,user_id,prod['name'],prod['id'],prod['price_admin'],'submitted',0,delivery_mode,payment_type,
           'Foot — Plantar','Left','Initial note',
           'Alice Smith','5555555555','123 Main St','City','ST','12345',
           sign_name,sign_title,
           icd10_primary,'',wlen,wwid,wdep,
           'Diabetic ulcer','II',last_eval_date,start_date,freq_per_week,qty_per_change,duration_days,refills_allowed,additional_instructions,prod['cpt_code']))
con.commit()

# Verify rows
p_row=c.execute('SELECT * FROM patients WHERE id=?',(pid,)).fetchone()
o_row=c.execute('SELECT * FROM orders WHERE id=?',(oid,)).fetchone()

if not p_row:
    print('FAIL: patient not found')
    raise SystemExit(1)
if not o_row:
    print('FAIL: order not found')
    raise SystemExit(1)

print('PASS: patient and order created')
print('Patient id:',p_row['id'],'MRN:',p_row['mrn'])
print('Order id:',o_row['id'],'Product:',o_row['product'],'Status:',o_row['status'])

con.close()
