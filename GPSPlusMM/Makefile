ARCHS = arm64 arm64e
TARGET = iphone:clang:latest:15.0
INSTALL_TARGET_PROCESSES = SpringBoard

include $(THEOS)/makefiles/common.mk

TWEAK_NAME = GPSPlusMM
GPSPlusMM_FILES = Sources/Tweak.mm Sources/GPSettings.mm Sources/GPLocationEngine.mm Sources/GPFavoritesStore.mm Sources/GPOverlayController.mm
GPSPlusMM_CFLAGS = -fobjc-arc
GPSPlusMM_FRAMEWORKS = UIKit Foundation CoreLocation MapKit

include $(THEOS_MAKE_PATH)/tweak.mk
