"""
Hardware: Lead colours are not consistent!
On mine:
	pin 1, red, +5v
        pin 2, blue, rx (connect to tx)
	pin 3, yellow, tx (connect to rx)
	pin 4, green, gnd
"""

import sys,serial,time
import numpy as np

if(len(sys.argv) < 3):
        print("USAGE: python3 distance.py device n-measurements")
        quit()

DEV = sys.argv[1]
NUM = int(sys.argv[2])

ser = serial.Serial(DEV, 115200,timeout=0)

def tfluna_read():
    for i in range(100): # Give up after 100 attempts; i.e. don't hang indefinitely
        counter = ser.in_waiting # Wait for 8 bytes
        if counter <= 8:
	        pass
        else:
            bytes_serial = ser.read(9) # read 9 bytes
            ser.reset_input_buffer() # reset buffer

            if bytes_serial[0] == 0x59 and bytes_serial[1] == 0x59: # check first two bytes
                distance = bytes_serial[2] + bytes_serial[3]*256 # distance in next two bytes
                strength = bytes_serial[4] + bytes_serial[5]*256 # signal strength in next two bytes
                temperature = bytes_serial[6] + bytes_serial[7]*256 # temp in next two bytes
                temperature = (temperature/8.0) - 256.0 # temp scaling and offset
                return distance/100.0,strength,temperature

    return False,False,False

if ser.isOpen() == False:
    ser.open()

for i in range(NUM):
    distance,strength,temperature = tfluna_read()
    if(distance == False):
        print('Failed')
    else:
        print('Distance: {0:2.2f} cm, Strength: {1:2.0f} / 65535 (16-bit), Chip Temperature: {2:2.1f} C'.\
           format(distance * 100,strength,temperature))

ser.close()
