import RPi.GPIO as GPIO
import time
import sys

GPIO.setmode(GPIO.BOARD)

if(len(sys.argv) < 4):
        print "USAGE: trigger-pin echo-pin measurements"
        quit()

TRIG = int(sys.argv[1])
ECHO = int(sys.argv[2])
NUM = int(sys.argv[3])

print "Distance Measurement In Progress"

GPIO.setup(TRIG,GPIO.OUT)
GPIO.setup(ECHO,GPIO.IN)

class SenseException(Exception):
        pass

n = 0
fail = 0
while n < NUM:
        GPIO.output(TRIG, False)
        time.sleep(0.5)

        sys.stdout.write(".")
        sys.stdout.flush()

        GPIO.output(TRIG, True)
        time.sleep(0.001)
        GPIO.output(TRIG, False)
        
        try:
                mstart = time.time()

                i = 0
                pulse_start = time.time()
                while GPIO.input(ECHO)==0:
                        pulse_start = time.time()
                        i += 1
                        if(i > 50000 and pulse_start > mstart + 0.2 ):
                                raise SenseException("Timed out during echo wait")

                i = 0
                while GPIO.input(ECHO)==1:
                        pulse_end = time.time()
                        i += 1
                        if(i > 50000 and pulse_end > mstart + 0.2 ):
                                raise SenseException("Timed out during echo phase")
                                
                n = n + 1
                
                pulse_duration = pulse_end - pulse_start
                distance = pulse_duration * 34300 / 2;
                distance = round(distance, 2)

                print "Distance:",distance,"cm"," (t=",round(pulse_duration,6),")"
                        
        except SenseException as e:
                fail = fail + 1;
                
                if(fail > NUM * 10):
                        print "Too many failed measurements - quitting :("
                        break

GPIO.cleanup()
