ARCHS = arm64 arm64e
TARGET = iphone:clang:latest:14.0
INSTALL_TARGET_PROCESSES = SpringBoard

include $(THEOS)/makefiles/common.mk

TWEAK_NAME = GPSPlus
GPSPlus_FILES = Tweak.mm client/GPSAllRequests.mm
GPSPlus_CFLAGS = -fobjc-arc
GPSPlus_FRAMEWORKS = Foundation UIKit

include $(THEOS_MAKE_PATH)/tweak.mk
