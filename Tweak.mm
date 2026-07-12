#import <Foundation/Foundation.h>

__attribute__((constructor)) static void GPSPlusInitialize(void) {
    @autoreleasepool {
        NSLog(@"[GPSPlus] Loaded successfully");
    }
}
