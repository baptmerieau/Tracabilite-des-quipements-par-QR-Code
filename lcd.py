!/usr/bin/env python3
import time
import mysql.connector
from RPLCD.i2c import CharLCD

DB_CONFIG = {
    "host": "localhost",
    "user": "tracabilite",
    "password": "MotDePasseFort123!",
    "database": "tracabilite"
}

lcd = CharLCD(
    i2c_expander='PCF8574',
    address=0x27,
    port=1,
    cols=20,
    rows=4,
    charmap='A00'
)

def get_last_scanned():
    conn = mysql.connector.connect(**DB_CONFIG)
    cur = conn.cursor()
    cur.execute("""
        SELECT e.nom, e.statut, e.qr_code
        FROM scan_pending s
        JOIN equipements e ON e.qr_code = s.qr_code
        ORDER BY s.first_scan_at DESC
        LIMIT 1
    """)
    row = cur.fetchone()
    cur.close()
    conn.close()
    return row

def show_equipment(name, status, qr):
    lcd.clear()
    lcd.cursor_pos = (0, 0)
    lcd.write_string("Dernier scan:")
    lcd.cursor_pos = (1, 0)
    lcd.write_string(name[:20].ljust(20))
    lcd.cursor_pos = (2, 0)
    lcd.write_string(status[:20].ljust(20))
    lcd.cursor_pos = (3, 0)
    lcd.write_string(qr[:20].ljust(20))
try:
    while True:
        try:
            row = get_last_scanned()
            if row:
                name, status, qr = row
                show_equipment(name, status, qr)
            else:
                lcd.clear()
                lcd.write_string("Aucun scan")
            time.sleep(1)

        except mysql.connector.Error as e:
            lcd.clear()
            lcd.write_string("Erreur BDD")
            lcd.cursor_pos = (1, 0)
            lcd.write_string(str(e)[:20].ljust(20))
            time.sleep(3)

except KeyboardInterrupt:
    lcd.clear()
    lcd.close(clear=True)
