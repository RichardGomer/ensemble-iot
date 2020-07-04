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
  msg = [1, (8+7)<<4, 0, 0] # single ended reading on channel 7
  bprint(msg)
  adc = spi.xfer2(msg)
  bprint(adc)
  data1 = ((adc[1]) << 8) & 0b1111111111 # this shifts the 2 LSBs to the correct position @ bit10/9 and truncates it to 10 bits
  data2 = adc[2] # to be added to the remaining bits to give the final 10-bit answer

  bprint([data1, data2])
  return data1 + data2

while True:
  output = analogInput()

  print(":: {}".format(output))
  sleep(2)
