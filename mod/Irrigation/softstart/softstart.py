
import RPi.GPIO as GPIO
import time
import math
import sys
import signal

if(len(sys.argv) < 3):
    print("USAGE: python3 softstart.py physpin intensity%");
    sys.exit(1); # Exit with general error code

quit = False;
def signal_handler(sig, frame):
    global quit
    print('Received SIGINT')
    quit = True;

signal.signal(signal.SIGINT, signal_handler)

pin = int(sys.argv[1])
intensity = int(sys.argv[2])

GPIO.setmode(GPIO.BOARD)
GPIO.setup(pin, GPIO.OUT)
pwm = GPIO.PWM(pin, 250) # second number is pwm Hz

print("Soft start/stop on physical pin " + str(pin) + " at " + str(intensity) + "%")

print("\nCtrl-C to end\n")
dc=0
pwm.start(dc)

try:

  for dc in range(0, intensity + 1, 2):
    pwm.ChangeDutyCycle(dc)
    time.sleep(0.1 / math.ceil((dc+1)/3));
    print(str(dc) + "%  ", end="\r")

  while True and not quit:
      time.sleep(1);

except KeyboardInterrupt:
  print("Interrupt received")

for dc in range(intensity, 0, -2):
  pwm.ChangeDutyCycle(dc)
  time.sleep(0.05 / math.ceil((dc+1)/3));
  print(str(dc) + "%  ", end="\r")

print("Done")
pwm.stop()                         # stop PWM
GPIO.cleanup()                     # resets GPIO ports used back to input mode
