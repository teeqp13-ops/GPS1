#import <Foundation/Foundation.h>
#import <CoreLocation/CoreLocation.h>

NS_ASSUME_NONNULL_BEGIN
@interface GPSettings : NSObject
@property(nonatomic, assign) BOOL simulationEnabled;
@property(nonatomic, assign) BOOL driftEnabled;
@property(nonatomic, assign) BOOL scheduleEnabled;
@property(nonatomic, assign) CLLocationCoordinate2D selectedCoordinate;
@property(nonatomic, copy) NSString *selectedName;
+ (instancetype)shared;
- (void)load;
- (void)save;
@end
NS_ASSUME_NONNULL_END
