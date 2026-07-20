#import <Foundation/Foundation.h>
#import <CoreLocation/CoreLocation.h>

NS_ASSUME_NONNULL_BEGIN
extern NSNotificationName const GPLocationEngineDidUpdateNotification;
@interface GPLocationEngine : NSObject
@property(nonatomic, readonly) CLLocation *currentSimulatedLocation;
+ (instancetype)shared;
- (void)start;
- (void)stop;
- (void)applyCoordinate:(CLLocationCoordinate2D)coordinate;
@end
NS_ASSUME_NONNULL_END
