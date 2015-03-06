"""
Python Image Processor
"""

from kaa import imlib2
from sys import argv
import glob
import pprint
import time
try:
    import json
except ImportError:
    import simplejson as json

#Begin Functions#

def fetch_sort(folder, type):
    files       = glob.glob(str(folder)+'/*.'+str(type))
    files.sort()
    file_cnt    = len(files)
    
    return files, file_cnt

def overlay(background, watermark, x, y, dest):
    img     = imlib2.open(background)
    img2    = imlib2.open(watermark)
    img.blend(img2, src_pos=(x, y))
    img.save(dest)

def calculate_tween_vals(current_x, current_y, future_x, future_y, duration):
    x_speed         = ( future_x - current_x ) / (duration-1)	#Get X Speed
    y_speed	        = ( future_y - current_y ) / (duration-1)	#Get Y Speed
    x_new_position	= current_x   #Placeholder for position per frame
    y_new_position	= current_y   #             ''
    values          = [(current_x, current_y)]

    for x in range (1, (duration-1)):
        x_new_position  = x_new_position + x_speed
        y_new_position  = y_new_position + y_speed
        values.append(x)
        values[x]       = (x_new_position, y_new_position)

    values.append(duration-1)
    values[duration-1] = (future_x, future_y)

    return values

def tween(bgim_folder, type, watermark, start_x, start_y, end_x, end_y):
    files, file_cnt = fetch_sort(bgim_folder, type)
    tween_v     = calculate_tween_vals(start_x, start_y, end_x, end_y, file_cnt)
    itinerator  = 0
    
    for x in files:
        overlay(x, watermark, tween_v[itinerator][0], tween_v[itinerator][1], x)
        itinerator  = itinerator + 1

#End Functions#

print 'Python Image Processor'

if len(argv) != 8:
    print 'Error: invalid number of arguments'

if str(argv[1]) == 'tween':
    tween(argv[2], argv[3], argv[4], int(argv[5]), int(argv[6]), int(argv[7]), int(argv[8]))
elif str(argv[1]) == 'overlay':
    overlay(argv[2], argv[3], argv[4], argv[5], argv[6])
else:
    print '(!)Function not recognized'
    