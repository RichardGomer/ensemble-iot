import spidev # To communicate with SPI devices
from pprint import pprint
from time import sleep  # To add delay

# Start SPI connection
spi = spidev.SpiDev() # Created an object
spi.open(0,0)

def bprint(list):
  pprint(list)
  for i in range(0, len(list)):
    print("{0:08b} ".format(list[i]), end="")

  print("\n")

# Read MCP3008 data
def analogInput():
  spi.max_speed_hz = 100000
  msg = [1, (7)<<4, 0, 0]
  bprint(msg)
  adc = spi.xfer2(msg)
  bprint(adc)
  data = ((adc[1]&3) << 8) + adc[2]
  return data

# Below function will convert data to voltage
def Volts(data):
  volts = (data * 3.3) / float(1023)
  volts = round(volts, ) # Round off to 2 decimal places
  return volts

while True:
  output = analogInput()

  print(":: {}".format(output))
  sleep(2)
