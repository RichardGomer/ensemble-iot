
import import RPi.GPIO as GPIO
import time
import math

GPIO.setmode(GPIO.BOARD)

GPIO.setup(pin, GPIO.OUT)
pwm = GPIO.PWM(pin, 250) # second number is pwm Hz

if(argv.len < 3):
    print("USAGE: python3 softstart.py physpin intensity%");
    sys.exit(1); # Exit with general error code

pin = argv[1]
intensity = argv[2]

print("Soft start/stop on physical pin " + str(pin) + " at " + str(intensity) + "%")

print("\nCtrl-C to end\n")
dc=0
pwm.start(dc)

try:

  for dc in range(0, intensity + 1, 2):
    pwm.ChangeDutyCycle(dc)
    time.sleep(0.1 / math.ceil((dc+1)/3));
    print(str(dc) + "%  ", end="\r")

  while True:
      sleep(1);

except KeyboardInterrupt:
  print("Interrupt received")

for dc in range(intensity, 0, -2):
  pwm.ChangeDutyCycle(dc)
  time.sleep(0.05 / math.ceil((dc+1)/3));
  print(str(dc) + "%  ", end="\r")

pwm.stop()                         # stop PWM
GPIO.cleanup()                     # resets GPIO ports used back to input mode
